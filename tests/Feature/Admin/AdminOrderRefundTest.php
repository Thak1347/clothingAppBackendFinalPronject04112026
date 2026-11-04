<?php

namespace Tests\Feature\Admin;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_record_manual_refund_initiation_and_completion(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = Order::factory()->forUser(User::factory()->create())->create([
            'fulfillment_status' => FulfillmentStatus::Cancelled,
            'payment_status' => PaymentStatus::Paid,
        ]);
        $payment = Payment::factory()->for($order)->create([
            'amount_minor' => 10000,
            'status' => PaymentStatus::Paid,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/refund", [
                'action' => 'initiated',
                'amount_minor' => 4000,
                'reason' => 'Refund requested after cancellation',
            ])
            ->assertOk()
            ->assertJsonPath('data.refund_pending_minor', 4000)
            ->assertJsonPath('data.amount_paid_minor', 10000);

        $this->assertSame(PaymentStatus::RefundPending, $order->fresh()->payment_status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/refund", [
                'action' => 'completed',
                'reason' => 'ABA refund reference REF-2026-001',
            ])
            ->assertOk()
            ->assertJsonPath('data.refunded_minor', 4000)
            ->assertJsonPath('data.refund_pending_minor', 0)
            ->assertJsonPath('data.payment_status', 'partially_refunded');

        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->fresh()->payment_status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_admin_cannot_record_refund_above_received_balance_or_complete_twice(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = Order::factory()->forUser(User::factory()->create())->create([
            'fulfillment_status' => FulfillmentStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
        ]);
        Payment::factory()->for($order)->create([
            'amount_minor' => 5000,
            'status' => PaymentStatus::Paid,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/refund", [
                'action' => 'initiated',
                'amount_minor' => 5001,
                'reason' => 'Exceeds amount paid',
            ])
            ->assertUnprocessable();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/refund", [
                'action' => 'initiated',
                'amount_minor' => 5000,
                'reason' => 'Refund after delivery issue',
            ])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/refund", [
                'action' => 'completed',
                'reason' => 'Provider reference REF-2026-002',
            ])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/refund", [
                'action' => 'completed',
                'reason' => 'Duplicate completion',
            ])
            ->assertUnprocessable();
    }
}
