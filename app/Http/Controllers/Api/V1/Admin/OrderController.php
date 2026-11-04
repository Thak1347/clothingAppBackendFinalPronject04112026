<?php
namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\FulfillmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderActivity;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index()
    {
        return OrderResource::collection(Order::with(['items', 'payment', 'activities.actor'])->latest()->paginate());
    }

    public function updateFulfillment(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => ['required', 'in:shipped,delivered,cancelled'],
        ]);
        $from = $order->fulfillment_status->value;
        if ($data['status'] === 'shipped') {
            abort_unless($order->fulfillment_status === FulfillmentStatus::Processing, 422, 'Only processing orders can be shipped.');
            abort_unless($order->payment_status === \App\Enums\PaymentStatus::PartiallyPaid || $order->payment_status === \App\Enums\PaymentStatus::Paid, 422, 'Confirm the required payment before shipping.');
        }
        if ($data['status'] === 'delivered') {
            abort_unless($order->fulfillment_status === FulfillmentStatus::Shipped, 422, 'Only shipped orders can be delivered.');
            if ($order->payment_method === 'cash') abort_unless((int) $order->cod_balance_paid_minor === max(0, (int) $order->total_minor - (int) ($order->payment?->amount_minor ?? 0)), 422, 'Record the remaining COD cash balance before marking delivered.');
            else abort_unless($order->payment_status === \App\Enums\PaymentStatus::Paid, 422, 'Confirm full payment before delivery.');
        }
        abort_unless(in_array($from, [FulfillmentStatus::PendingVerification->value, FulfillmentStatus::AwaitingDeposit->value, FulfillmentStatus::PendingApproval->value, FulfillmentStatus::PendingPayment->value, FulfillmentStatus::CancellationRequested->value, FulfillmentStatus::Processing->value, FulfillmentStatus::Shipped->value], true) || $data['status'] !== 'cancelled', 422, 'Order cannot transition from its current state.');
        if ($data['status'] === 'cancelled') abort_unless(!in_array($order->fulfillment_status, [FulfillmentStatus::Shipped, FulfillmentStatus::Delivered, FulfillmentStatus::Cancelled], true), 422, 'This order can no longer be cancelled.');
        $order->update(['fulfillment_status' => FulfillmentStatus::from($data['status'])]);
        if ($data['status'] === 'delivered' && $order->cod_home_photo_path) $order->update(['cod_home_photo_delete_at' => now()->addDays(90)]);
        if ($data['status'] === 'cancelled') {
            if (!$order->inventory_released_at) {
                app(InventoryService::class)->release($order->items()->get(['product_variant_id', 'quantity'])->toArray());
                $order->update(['inventory_released_at' => now()]);
            }
            if ($order->cod_home_photo_path) {
                Storage::disk('local')->delete($order->cod_home_photo_path);
                $order->update(['cod_home_photo_path' => null]);
            }
            if ($order->payment_method === 'cash') $order->update(['cod_verification_status' => 'cancelled']);
        }
        OrderActivity::record($order, $request->user()->id, 'fulfillment_changed', $from, $data['status']);
        return new OrderResource($order->fresh()->load(['items', 'payment']));
    }

    public function codDocument(Order $order): StreamedResponse
    {
        abort_unless($order->payment_method === 'cash' && $order->cod_home_photo_path, 404);
        abort_unless(Storage::disk('local')->exists($order->cod_home_photo_path), 404);
        return Storage::disk('local')->response($order->cod_home_photo_path);
    }

    public function reviewCod(Request $request, Order $order)
    {
        $order->loadMissing('payment');
        $data = $request->validate(['decision' => ['required', 'in:approve,reject,request_correction'], 'reason' => ['required_if:decision,reject,request_correction', 'nullable', 'string', 'max:1000']]);
        abort_unless($order->payment_method === 'cash' && in_array($order->cod_verification_status, ['pending', 'correction_required'], true) && $order->fulfillment_status === FulfillmentStatus::PendingVerification, 422, 'This COD application cannot be reviewed in its current state.');
        $verified = $data['decision'] === 'approve';
        $oldStatus = $order->fulfillment_status->value;
        $newStatus = $verified ? FulfillmentStatus::AwaitingDeposit : ($data['decision'] === 'reject' ? FulfillmentStatus::Rejected : FulfillmentStatus::PendingVerification);
        $order->update([
            'cod_verification_status' => $verified ? 'verified' : ($data['decision'] === 'reject' ? 'rejected' : 'correction_required'),
            'cod_verified_at' => $verified ? now() : null,
            'cod_reviewed_by' => $request->user()->id,
            'cod_reviewed_at' => now(),
            'cod_review_reason' => $data['reason'] ?? null,
            'fulfillment_status' => $newStatus,
        ]);
        OrderActivity::record($order, $request->user()->id, 'cod_' . $data['decision'], $oldStatus, $newStatus->value, ['reason' => $data['reason'] ?? null]);
        $message = match ($data['decision']) {
            'approve' => 'Your COD details were approved. You can now pay the required 50% deposit with ABA Pay.',
            'reject' => 'Your COD request was rejected. Open the order to review the decision and payment options.',
            default => 'Your COD details need correction. Open the order to see the admin note and resubmit.',
        };
        \App\Jobs\SendPushNotification::dispatch(
            $order->user_id,
            'COD verification update',
            $message,
        )->afterCommit();
        if ($data['decision'] === 'reject' && $order->cod_home_photo_path) {
            Storage::disk('local')->delete($order->cod_home_photo_path);
            $order->update(['cod_home_photo_path' => null]);
        }
        if ($data['decision'] === 'reject' && !$order->inventory_released_at) {
            app(InventoryService::class)->release($order->items()->get(['product_variant_id', 'quantity'])->toArray());
            $order->update(['inventory_released_at' => now()]);
        }
        return new OrderResource($order->fresh()->load(['items', 'payment']));
    }

    public function reviewCancellation(Request $request, Order $order)
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:1000']]);
        abort_unless($order->fulfillment_status === FulfillmentStatus::CancellationRequested, 422, 'There is no pending cancellation request.');
        $from = $order->fulfillment_status->value;
        if ($data['decision'] === 'approve') {
            $order->update(['fulfillment_status' => FulfillmentStatus::Cancelled, 'cancellation_review_reason' => $data['reason'] ?? null]);
            if (!$order->inventory_released_at) {
                app(InventoryService::class)->release($order->items()->get(['product_variant_id', 'quantity'])->toArray());
                $order->update(['inventory_released_at' => now()]);
            }
            if ($order->payment_method === 'cash') $order->update(['cod_verification_status' => 'cancelled']);
            if ($order->cod_home_photo_path) {
                Storage::disk('local')->delete($order->cod_home_photo_path);
                $order->update(['cod_home_photo_path' => null]);
            }
            $received = in_array($order->payment?->status, [\App\Enums\PaymentStatus::Paid, \App\Enums\PaymentStatus::PartiallyPaid, \App\Enums\PaymentStatus::PartiallyRefunded, \App\Enums\PaymentStatus::Refunded], true) ? (int) ($order->payment?->amount_minor ?? 0) : 0;
            $received += (int) $order->cod_balance_paid_minor;
            OrderActivity::record($order, $request->user()->id, 'cancellation_approved', $from, FulfillmentStatus::Cancelled->value, ['reason' => $data['reason'] ?? null, 'refund_required' => $received > (int) $order->refunded_minor, 'amount_received_minor' => $received]);
        } else {
            $previous = FulfillmentStatus::tryFrom((string) $order->cancellation_requested_from);
            abort_unless($previous !== null && in_array($previous, [FulfillmentStatus::PendingPayment, FulfillmentStatus::PendingVerification, FulfillmentStatus::AwaitingDeposit, FulfillmentStatus::PendingApproval, FulfillmentStatus::Processing], true), 422, 'The prior order status is invalid.');
            if (in_array($previous, [FulfillmentStatus::AwaitingDeposit, FulfillmentStatus::PendingApproval], true)
                && $order->payment_status === \App\Enums\PaymentStatus::PartiallyPaid) {
                $previous = FulfillmentStatus::Processing;
            }
            if ($previous === FulfillmentStatus::PendingPayment && $order->payment_status === \App\Enums\PaymentStatus::Paid) {
                $previous = FulfillmentStatus::Processing;
            }
            $order->update(['fulfillment_status' => $previous, 'cancellation_requested_from' => null, 'cancellation_review_reason' => $data['reason'] ?? null]);
            OrderActivity::record($order, $request->user()->id, 'cancellation_rejected', $from, $previous->value, ['reason' => $data['reason'] ?? null]);
        }
        return new OrderResource($order->fresh()->load(['items', 'payment', 'activities.actor']));
    }

    public function approveCodOrder(Request $request, Order $order)
    {
        abort_unless($order->payment_method === 'cash' && $order->fulfillment_status === FulfillmentStatus::PendingApproval && $order->cod_verification_status === 'verified' && $order->payment_status === \App\Enums\PaymentStatus::PartiallyPaid, 422, 'COD documents and the verified 50% deposit are both required.');
        $order->update(['fulfillment_status' => FulfillmentStatus::Processing]);
        OrderActivity::record($order, $request->user()->id, 'cod_order_approved', FulfillmentStatus::PendingApproval->value, FulfillmentStatus::Processing->value);
        \App\Jobs\SendPushNotification::dispatch(
            $order->user_id,
            'COD order approved',
            "Order {$order->order_number} is being prepared for shipment.",
        )->afterCommit();
        return new OrderResource($order->fresh()->load(['items', 'payment', 'activities.actor']));
    }

    public function recordCodBalance(Request $request, Order $order)
    {
        $orderId = $order->id;
        $updated = \Illuminate\Support\Facades\DB::transaction(function () use ($orderId, $request) {
            $order = Order::with('payment')->whereKey($orderId)->lockForUpdate()->firstOrFail();
            abort_unless($order->payment_method === 'cash' && $order->fulfillment_status === FulfillmentStatus::Shipped, 422, 'COD balance can only be collected for a shipped COD order.');
            $deposit = (int) ($order->payment?->amount_minor ?? 0);
            $due = max(0, (int) $order->total_minor - $deposit);
            abort_unless($order->payment_status === \App\Enums\PaymentStatus::PartiallyPaid, 422, 'The verified 50% ABA Pay deposit must be received first.');
            abort_unless($due > 0 && (int) $order->cod_balance_paid_minor === 0, 422, 'COD balance is already recorded or no balance is due.');

            $from = $order->fulfillment_status->value;
            $order->update([
                'cod_balance_paid_minor' => $due,
                'cod_balance_paid_at' => now(),
                'cod_balance_received_by' => $request->user()->id,
                'payment_status' => \App\Enums\PaymentStatus::Paid,
                'fulfillment_status' => FulfillmentStatus::Delivered,
                'cod_home_photo_delete_at' => $order->cod_home_photo_path ? now()->addDays(90) : null,
            ]);
            OrderActivity::record($order, $request->user()->id, 'cod_balance_collected', $from, $from, ['amount_minor' => $due, 'method' => 'cash_on_delivery']);
            OrderActivity::record($order, $request->user()->id, 'fulfillment_changed', FulfillmentStatus::Shipped->value, FulfillmentStatus::Delivered->value);
            app(\App\Services\ReceiptService::class)->finalizeCodBalance($order->payment, $due, (int) $request->user()->id);

            \App\Jobs\SendPushNotification::dispatch(
                $order->user_id,
                'Order delivered and paid',
                "The remaining COD balance for order {$order->order_number} was recorded. Your final receipt is ready.",
            )->afterCommit();

            return $order->fresh()->load(['items', 'payment', 'activities.actor']);
        });

        return new OrderResource($updated);
    }

    public function recordRefund(Request $request, Order $order)
    {
        $data = $request->validate([
            'action' => ['required', 'in:initiated,completed'],
            'amount_minor' => ['required_if:action,initiated', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $updated = DB::transaction(function () use ($data, $order, $request) {
            $locked = Order::with('payment')->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->fulfillment_status, [FulfillmentStatus::Cancelled, FulfillmentStatus::Rejected, FulfillmentStatus::Delivered], true), 422, 'A refund can only be recorded for a terminal order.');
            $grossPaid = (in_array($locked->payment_status, [\App\Enums\PaymentStatus::PartiallyPaid, \App\Enums\PaymentStatus::Paid, \App\Enums\PaymentStatus::RefundPending, \App\Enums\PaymentStatus::PartiallyRefunded, \App\Enums\PaymentStatus::Refunded], true) ? (int) ($locked->payment?->amount_minor ?? 0) : 0) + (int) $locked->cod_balance_paid_minor;

            if ($data['action'] === 'initiated') {
                $amount = (int) $data['amount_minor'];
                abort_unless($amount <= $grossPaid - (int) $locked->refunded_minor - (int) $locked->refund_pending_minor, 422, 'Refund amount exceeds the amount received and not yet refunded.');
                $locked->update(['refund_pending_minor' => (int) $locked->refund_pending_minor + $amount, 'payment_status' => \App\Enums\PaymentStatus::RefundPending]);
                OrderActivity::record($locked, $request->user()->id, 'refund_initiated', $locked->fulfillment_status->value, $locked->fulfillment_status->value, ['amount_minor' => $amount, 'reason' => $data['reason']]);
            } else {
                $amount = (int) $locked->refund_pending_minor;
                abort_unless($amount > 0, 422, 'There is no pending refund to complete.');
                $refunded = (int) $locked->refunded_minor + $amount;
                $paymentStatus = $refunded >= $grossPaid ? \App\Enums\PaymentStatus::Refunded : \App\Enums\PaymentStatus::PartiallyRefunded;
                $locked->update(['refunded_minor' => $refunded, 'refund_pending_minor' => 0, 'payment_status' => $paymentStatus]);
                OrderActivity::record($locked, $request->user()->id, 'refund_completed', $locked->fulfillment_status->value, $locked->fulfillment_status->value, ['amount_minor' => $amount, 'cumulative_refunded_minor' => $refunded, 'reason' => $data['reason']]);
            }

            return $locked->fresh()->load(['items', 'payment', 'activities.actor']);
        });

        return new OrderResource($updated);
    }
}
