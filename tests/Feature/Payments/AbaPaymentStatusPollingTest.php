<?php

namespace Tests\Feature\Payments;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AbaPaymentStatusPollingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.payment.driver' => 'khqr',
            'services.payment.anajakpay.profile_id' => 'test-profile',
            'services.payment.anajakpay.secret' => 'test-secret',
            'services.payment.khqr.base_url' => 'https://anajakpay.com/api',
            'services.payment.khqr.timeout' => 1,
        ]);
    }

    public function test_poll_verifies_aba_payment_with_check_trans_v2_and_marks_it_paid(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->forUser($user)->create([
            'fulfillment_status' => FulfillmentStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);
        $payment = Payment::factory()->for($order)->create([
            'provider' => 'aba_pay',
            'payment_reference' => 'PAY-ATIU4YMJ5G',
            'amount_minor' => 1234,
            'status' => PaymentStatus::Pending,
        ]);

        Http::fake(['*check-transv2-khqrcc' => Http::response([
            'responseCode' => 0,
            'data' => [
                'transaction_id' => 'PAY-ATIU4YMJ5G',
                'status' => 'success',
                'amount' => '12.34',
                'currency' => 'USD',
            ],
        ])]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/payment-status")
            ->assertOk()
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('order.payment.status', 'paid');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);

        Http::assertSent(function (HttpRequest $request): bool {
            $fields = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://anajakpay.com/api/test-profile/payment-gateway/v1/payments/check-transv2-khqrcc'
                && $fields['transaction_id'] === 'PAY-ATIU4YMJ5G'
                && hash_equals(sha1('test-secretPAY-ATIU4YMJ5G'), $fields['hash']);
        });
    }

    public function test_poll_does_not_mark_aba_payment_paid_when_provider_amount_differs(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->forUser($user)->create([
            'fulfillment_status' => FulfillmentStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);
        $payment = Payment::factory()->for($order)->create([
            'provider' => 'aba_pay',
            'payment_reference' => 'PAY-ATIU4YMJ5G',
            'amount_minor' => 1234,
            'status' => PaymentStatus::Pending,
        ]);

        Http::fake(['*check-transv2-khqrcc' => Http::response([
            'responseCode' => 0,
            'data' => [
                'transaction_id' => 'PAY-ATIU4YMJ5G',
                'status' => 'success',
                'amount' => '99.00',
                'currency' => 'USD',
            ],
        ])]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/payment-status")
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    public function test_poll_verifies_hosted_aba_checkout_with_check_trans_v2(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->forUser($user)->create([
            'fulfillment_status' => FulfillmentStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);
        Payment::factory()->for($order)->create([
            'provider' => 'khqr_checkout',
            'payment_reference' => 'PAY-HOSTED1234',
            'amount_minor' => 1234,
            'status' => PaymentStatus::Pending,
        ]);

        Http::fake(['*check-transv2-khqrcc' => Http::response([
            'responseCode' => 0,
            'data' => [
                'transaction_id' => 'PAY-HOSTED1234',
                'status' => 'success',
                'amount' => '12.34',
                'currency' => 'USD',
            ],
        ])]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/payment-status")
            ->assertOk()
            ->assertJsonPath('status', 'paid');

        Http::assertSent(fn (HttpRequest $request): bool =>
            str_ends_with($request->url(), '/check-transv2-khqrcc'));
    }
}
