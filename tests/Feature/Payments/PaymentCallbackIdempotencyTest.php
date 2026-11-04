<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentStatus;
use App\Enums\FulfillmentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentCallbackIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function signedPayload(Payment $payment, string $status = 'SUCCESS', ?string $amount = null): array
    {
        $secret = 'test-secret';
        config(['services.payment.driver' => 'khqr', 'services.payment.khqr.secret_key' => $secret,
            'services.payment.khqr.profile_id' => 'profile', 'services.payment.khqr.timeout' => 1]);
        $payload = ['req_time' => '1761212560', 'transaction_id' => $payment->payment_reference,
            'amount' => $amount ?? number_format($payment->amount_minor / 100, 2, '.', ''), 'status' => $status];
        $payload['hash'] = hash('sha256', $secret . $payload['req_time'] . $payload['transaction_id'] . $payload['amount'] . $status);
        return $payload;
    }

    private function successCheckTrans(): void
    {
        Http::fake(['*check-trans*' => Http::response(['responseCode' => 0, 'data' => ['status' => 'success']])]);
    }

    private function successCheckTransV2(string $amount): void
    {
        Http::fake(['*check-transv2-khqrcc' => Http::response([
            'responseCode' => 0,
            'data' => ['status' => 'success', 'amount' => $amount, 'currency' => 'USD'],
        ])]);
    }

    public function test_valid_success_callback_pays_once_and_duplicate_is_idempotent(): void
    {
        $payment = Payment::factory()->create(['provider' => 'khqr', 'amount_minor' => 1234]);
        $this->successCheckTrans();
        $payload = $this->signedPayload($payment);
        $this->postJson('/api/v1/payments/callback', $payload)->assertOk();
        $this->postJson('/api/v1/payments/callback', $payload)->assertOk();
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(\App\Enums\FulfillmentStatus::Processing, $payment->order->fresh()->fulfillment_status);
        $this->assertSame(1, $payment->transactionBill()->count());
    }

    public function test_verified_cod_deposit_moves_approved_order_directly_to_processing(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->forUser($user)->create([
            'payment_method' => 'cash',
            'fulfillment_status' => FulfillmentStatus::AwaitingDeposit,
            'payment_status' => PaymentStatus::Pending,
            'cod_verification_status' => 'verified',
        ]);
        $payment = Payment::factory()->for($order)->create([
            'provider' => 'aba_pay',
            'amount_minor' => intdiv((int) $order->total_minor + 1, 2),
            'status' => PaymentStatus::Pending,
        ]);
        $this->successCheckTransV2(number_format($payment->amount_minor / 100, 2, '.', ''));

        $payload = $this->signedPayload($payment);
        $this->assertSame('paid', app(\App\Services\Payments\KhqrPaymentProvider::class)->verify($payload));
        $this->postJson('/api/v1/payments/callback', $payload)->assertOk();

        $this->assertSame(PaymentStatus::PartiallyPaid, $payment->fresh()->status);
        $this->assertSame(FulfillmentStatus::Processing, $order->fresh()->fulfillment_status);
        $this->assertSame(PaymentStatus::PartiallyPaid, $order->fresh()->payment_status);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payment = Payment::factory()->create(['provider' => 'khqr']);
        $payload = $this->signedPayload($payment);
        $payload['hash'] = 'bad';
        $this->postJson('/api/v1/payments/callback', $payload)->assertForbidden();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_unknown_reference_is_acknowledged_without_payment(): void
    {
        $payment = Payment::factory()->make(['payment_reference' => 'missing-reference']);
        $this->postJson('/api/v1/payments/callback', $this->signedPayload($payment))->assertOk();
    }

    public function test_incorrect_amount_is_rejected_without_paying(): void
    {
        $payment = Payment::factory()->create(['provider' => 'khqr', 'amount_minor' => 1234]);
        $this->postJson('/api/v1/payments/callback', $this->signedPayload($payment, 'SUCCESS', '99.00'))->assertOk();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_pending_callback_does_not_pay(): void
    {
        $payment = Payment::factory()->create(['provider' => 'khqr']);
        $this->postJson('/api/v1/payments/callback', $this->signedPayload($payment, 'PENDING'))->assertOk();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_hosted_checkout_success_url_accepts_provider_post_callback(): void
    {
        $payment = Payment::factory()->create(['provider' => 'khqr_checkout']);
        $payload = $this->signedPayload($payment, 'PENDING');
        $this->postJson('/api/v1/payments/khqr/success', $payload)->assertOk()->assertJson(['received' => true]);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_provider_timeout_is_acknowledged_without_payment(): void
    {
        $payment = Payment::factory()->create(['provider' => 'khqr']);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
        $this->postJson('/api/v1/payments/callback', $this->signedPayload($payment))->assertOk();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_redirect_paid_requires_valid_signature_and_provider_success(): void
    {
        $payment = Payment::factory()->create(['provider' => 'khqr_checkout', 'amount_minor' => 1234]);
        config(['services.payment.khqr.profile_key' => 'profile-key']);
        $this->successCheckTransV2('12.34');
        $payload = ['transaction_id' => $payment->payment_reference, 'success_time' => (string) time(), 'success_amount' => '12.34', 'bakong_hash' => 'bk'];
        $payload['success_hash'] = sha1('profile-key' . $payload['success_time'] . $payload['success_amount'] . $payload['bakong_hash'] . $payload['transaction_id']);
        $this->getJson('/api/v1/payments/khqr/success?' . http_build_query($payload))->assertOk()->assertJson(['payment_status' => 'paid']);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_redirect_remains_pending_without_signature(): void
    {
        $payment = Payment::factory()->create(['provider' => 'khqr_checkout']);
        $this->getJson('/api/v1/payments/khqr/success?transaction_id=' . $payment->payment_reference)->assertOk()->assertJson(['payment_status' => 'pending']);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_invalid_or_expired_redirect_signature_does_not_pay(): void
    {
        $payment = Payment::factory()->create(['provider' => 'khqr_checkout']);
        config(['services.payment.khqr.profile_key' => 'profile-key']);
        $payload = ['transaction_id' => $payment->payment_reference, 'success_time' => (string) (time() - 300),
            'success_amount' => number_format($payment->amount_minor / 100, 2, '.', ''), 'bakong_hash' => 'bk', 'success_hash' => 'invalid'];
        $this->getJson('/api/v1/payments/khqr/success?' . http_build_query($payload))->assertOk()->assertJson(['payment_status' => 'pending']);
    }
}
