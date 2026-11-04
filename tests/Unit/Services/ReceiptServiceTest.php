<?php

namespace Tests\Unit\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_cod_receipt_includes_deposit_and_cash_balance(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->forUser($user)->create([
            'payment_method' => 'cash',
            'fulfillment_status' => FulfillmentStatus::Shipped,
            'payment_status' => PaymentStatus::PartiallyPaid,
            'subtotal_minor' => 10001,
            'total_minor' => 10001,
            'delivery_fee_minor' => 0,
            'discount_minor' => 0,
        ]);
        $payment = Payment::factory()->for($order)->create([
            'provider' => 'aba_pay',
            'amount_minor' => 5001,
            'status' => PaymentStatus::PartiallyPaid,
            'verified_at' => now(),
        ]);

        $service = app(ReceiptService::class);
        $service->issue($payment);
        $order->update([
            'cod_balance_paid_minor' => 5000,
            'cod_balance_paid_at' => now(),
            'payment_status' => PaymentStatus::Paid,
            'fulfillment_status' => FulfillmentStatus::Delivered,
        ]);

        $receipt = $service->finalizeCodBalance($payment, 5000, $user->id);
        $payload = $receipt->payload;

        $this->assertSame('final', $payload['receipt_type']);
        $this->assertSame(10001, $payload['amount_minor']);
        $this->assertCount(2, $payload['payment_breakdown']);
        $this->assertSame(10001, array_sum(array_column($payload['payment_breakdown'], 'amount_minor')));
        $this->assertSame('cash_on_delivery', $payload['payment_breakdown'][1]['method']);
    }
}
