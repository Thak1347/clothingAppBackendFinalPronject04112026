<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AbaQrRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function configureProvider(): void
    {
        config([
            'services.payment.driver' => 'khqr',
            'services.payment.khqr.base_url' => 'https://anajakpay.com/api',
            'services.payment.khqr.profile_id' => 'test-profile',
            'services.payment.khqr.secret_key' => 'test-secret',
            'services.payment.khqr.webhook_url' => 'https://shop.example.test/api/v1/payments/callback',
        ]);
    }

    public function test_it_posts_the_documented_qr_api_fields_and_returns_the_raw_qr(): void
    {
        $this->configureProvider();
        Http::fake(['*qr-api-khqrcc' => Http::response([
            'responseCode' => 0,
            'data' => [
                'transaction_id' => 'PAY-TEST123456',
                'amount' => '25.00',
                'qr' => '000201-khqr-test',
                'qr_url' => 'https://anajakpay.com/api/qr/PAY-TEST123456',
                'md5' => 'qr-hash',
            ],
        ])]);

        $user = User::factory()->create();
        $order = Order::factory()->forUser($user)->create();
        $payment = Payment::factory()->for($order)->create([
            'provider' => 'mock',
            'payment_reference' => 'PAY-TEST123456',
            'amount_minor' => 2500,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/payments/{$payment->id}/initiate",
            ['provider' => 'aba_pay'],
        );

        $response->assertOk()
            ->assertJsonPath('provider', 'aba_pay')
            ->assertJsonPath('qr_string', '000201-khqr-test')
            ->assertJsonPath('qr_url', 'https://anajakpay.com/api/qr/PAY-TEST123456');
        Http::assertSent(function (HttpRequest $request): bool {
            $fields = $request->data();
            $expectedHash = sha1('test-secret'
                . $fields['transaction_id']
                . $fields['amount']
                . $fields['success_url']
                . $fields['remark']);

            return $request->method() === 'POST'
                && $request->url() === 'https://anajakpay.com/api/test-profile/payment-gateway/v1/payments/qr-api-khqrcc'
                && $fields['transaction_id'] === 'PAY-TEST123456'
                && $fields['amount'] === '25.00'
                && $fields['template'] === 'template4_color'
                && hash_equals($expectedHash, $fields['hash']);
        });
    }

    public function test_provider_retry_after_is_returned_to_the_customer_without_retrying(): void
    {
        $this->configureProvider();
        Http::fake(['*qr-api-khqrcc' => Http::response(
            ['responseCode' => 429, 'responseMessage' => 'Too Many Requests'],
            429,
            ['Retry-After' => '30'],
        )]);

        $user = User::factory()->create();
        $order = Order::factory()->forUser($user)->create();
        $payment = Payment::factory()->for($order)->create([
            'provider' => 'mock',
            'amount_minor' => 2500,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/payments/{$payment->id}/initiate",
            ['provider' => 'aba_pay'],
        );

        $response->assertStatus(429)
            ->assertHeader('Retry-After', '30')
            ->assertJsonPath('message', 'ABA Pay is temporarily rate-limited. Please try again in 30 seconds.');
        Http::assertSentCount(1);
        $this->assertSame('pending', $payment->fresh()->status->value);
    }
}
