<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderActivity;
use App\Jobs\SendPushNotification;
use App\Services\CheckoutService;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, CheckoutService $svc)
    {
        $cod = null;
        if ($request->input('payment_method') === 'cash') {
            $cod = $request->input('cod');
            $cod['home_photo_path'] = $request->file('cod.home_photo')->store('cod-documents', 'local');
            unset($cod['home_photo']);
        }
        try {
            $order = $svc->createOrder(
                $request->user()->id,
                $request->integer('address_id'),
                $request->input('items'),
                $cod,
                $request->input('payment_method'),
            );
        } catch (\Throwable $exception) {
            if ($cod['home_photo_path'] ?? null) \Illuminate\Support\Facades\Storage::disk('local')->delete($cod['home_photo_path']);
            throw $exception;
        }
        OrderActivity::record($order, $request->user()->id, 'order_placed', null, $order->fulfillment_status->value, ['payment_method' => $order->payment_method ?? $order->payment->provider]);
        SendPushNotification::dispatch(
            $request->user()->id,
            'Order placed',
            "Order {$order->order_number} was placed successfully.",
        )->afterCommit();
        if ($order->payment_method === 'cash') {
            \App\Jobs\SendTelegramNotification::dispatch(
                "COD verification requested for order {$order->order_number}. Review it in the admin dashboard.",
            )->afterCommit();
        }
        return new OrderResource($order->load(['items', 'payment', 'activities.actor']));
    }

    public function cancel(\Illuminate\Http\Request $request, Order $order)
    {
        $this->authorize('view', $order);
        abort_unless($request->user()->id === $order->user_id, 403);
        $orderId = $order->id;
        \Illuminate\Support\Facades\DB::transaction(function () use ($orderId, $request) {
            $order = Order::with('payment')->whereKey($orderId)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($order->fulfillment_status, [\App\Enums\FulfillmentStatus::PendingPayment, \App\Enums\FulfillmentStatus::PendingVerification, \App\Enums\FulfillmentStatus::AwaitingDeposit, \App\Enums\FulfillmentStatus::PendingApproval, \App\Enums\FulfillmentStatus::Processing], true), 422, 'This order can no longer request cancellation.');
            $from = $order->fulfillment_status->value;
            $order->update(['fulfillment_status' => \App\Enums\FulfillmentStatus::CancellationRequested, 'cancellation_requested_from' => $from]);
            OrderActivity::record($order, $request->user()->id, 'cancellation_requested', $from, \App\Enums\FulfillmentStatus::CancellationRequested->value);
        });
        return new OrderResource($order->fresh()->load(['items', 'payment', 'activities.actor']));
    }

    public function updateCodDetails(\Illuminate\Http\Request $request, Order $order)
    {
        $this->authorize('view', $order);
        abort_unless($request->user()->id === $order->user_id, 403);
        abort_unless($order->payment_method === 'cash' && $order->fulfillment_status === \App\Enums\FulfillmentStatus::PendingVerification && $order->cod_verification_status === 'correction_required' && in_array($order->payment_status, [\App\Enums\PaymentStatus::Unpaid, \App\Enums\PaymentStatus::Pending], true), 422, 'COD information cannot be changed in the current state.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'], 'telegram' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:500'], 'home_photo' => ['sometimes', 'image', 'max:5120'],
        ]);
        $oldPath = $order->cod_home_photo_path;
        if ($request->hasFile('home_photo')) $data['cod_home_photo_path'] = $request->file('home_photo')->store('cod-documents', 'local');
        $order->update([
            'cod_customer_name' => $data['name'], 'cod_phone' => $data['phone'], 'cod_email' => $data['email'],
            'cod_telegram' => $data['telegram'], 'cod_address' => $data['address'],
            'cod_home_photo_path' => $data['cod_home_photo_path'] ?? $oldPath,
            'cod_verification_status' => 'pending', 'cod_review_reason' => null, 'cod_reviewed_by' => null,
            'cod_reviewed_at' => null,
        ]);
        if (($data['cod_home_photo_path'] ?? null) && $oldPath) \Illuminate\Support\Facades\Storage::disk('local')->delete($oldPath);
        OrderActivity::record($order, $request->user()->id, 'cod_details_resubmitted', \App\Enums\FulfillmentStatus::PendingVerification->value, \App\Enums\FulfillmentStatus::PendingVerification->value);
        return new OrderResource($order->fresh()->load(['items', 'payment']));
    }

    public function switchCodToAba(\Illuminate\Http\Request $request, Order $order, \App\Services\InventoryService $inventory)
    {
        $this->authorize('view', $order);
        abort_unless($request->user()->id === $order->user_id, 403);
        abort_unless($order->payment_method === 'cash' && $order->cod_verification_status === 'rejected' && $order->fulfillment_status === \App\Enums\FulfillmentStatus::Rejected && $order->payment_status === \App\Enums\PaymentStatus::Unpaid, 422, 'This order cannot be changed to ABA Pay.');
        $oldStatus = $order->fulfillment_status->value;
        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $request, $inventory) {
            $inventory->reserve($order->items()->get(['product_variant_id', 'quantity'])->toArray());
            $order->update(['inventory_released_at' => null]);
            $order->update(['payment_method' => 'aba_pay', 'fulfillment_status' => \App\Enums\FulfillmentStatus::PendingPayment]);
            $order->payment()->update(['provider' => 'aba_pay', 'amount_minor' => (int) $order->total_minor, 'status' => \App\Enums\PaymentStatus::Pending]);
            $order->update(['payment_status' => \App\Enums\PaymentStatus::Pending]);
            OrderActivity::record($order, $request->user()->id, 'payment_method_changed', \App\Enums\FulfillmentStatus::Rejected->value, \App\Enums\FulfillmentStatus::PendingPayment->value, ['from' => 'cash', 'to' => 'aba_pay']);
        });
        if ($order->cod_home_photo_path) \Illuminate\Support\Facades\Storage::disk('local')->delete($order->cod_home_photo_path);
        $order->update(['cod_home_photo_path' => null]);
        return new OrderResource($order->fresh()->load(['items', 'payment', 'activities.actor']));
    }

    public function index(\Illuminate\Http\Request $request)
    {
        return OrderResource::collection(
            $request->user()->orders()->with(['items', 'payment', 'activities.actor'])->latest()->paginate()
        );
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);
        return new OrderResource($order->load(['items', 'payment', 'activities.actor']));
    }

    public function paymentStatus(\Illuminate\Http\Request $request, string $order)
    {
        // The Flutter app polls with the numeric order ID, while external links
        // and older clients may pass the human-readable order number.
        $model = $request->user()->orders()
            ->where(fn ($query) => $query->whereKey($order)->orWhere('order_number', $order))
            ->firstOrFail();
        $status = app(\App\Services\PaymentService::class)->poll($model);
        return response()->json(['status' => $status['status'], 'order' => new OrderResource($model->fresh()->load(['items', 'payment', 'activities.actor']))]);
    }
}
