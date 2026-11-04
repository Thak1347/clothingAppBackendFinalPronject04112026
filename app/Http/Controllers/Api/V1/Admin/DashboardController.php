<?php
namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'period' => ['sometimes', 'in:today,week,month'],
        ]);
        $period = $data['period'] ?? 'today';
        $start = match ($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->startOfDay(),
        };

        $paidPayments = Payment::query()
            ->where('status', PaymentStatus::Paid->value)
            ->where('verified_at', '>=', $start);
        $ordersInPeriod = Order::query()->where('created_at', '>=', $start);
        $lowStock = ProductVariant::query()->where('stock_quantity', '<=', 2);

        return response()->json([
            'period' => $period,
            'summary' => [
                'paid_total_minor' => (int) (clone $paidPayments)->sum('amount_minor'),
                'paid_payment_count' => (int) (clone $paidPayments)->count(),
                'orders_count' => (int) (clone $ordersInPeriod)->count(),
                'pending_payment_count' => Order::whereIn('fulfillment_status', [FulfillmentStatus::PendingPayment->value, FulfillmentStatus::PendingVerification->value, FulfillmentStatus::AwaitingDeposit->value])->count(),
                'pending_cod_approval_count' => Order::where('fulfillment_status', FulfillmentStatus::PendingApproval->value)->count(),
                'low_stock_count' => (int) (clone $lowStock)->count(),
                'active_products_count' => Product::where('is_active', true)->count(),
                'active_categories_count' => Category::where('is_active', true)->count(),
            ],
            'recent_orders' => Order::with(['items', 'payment'])
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Order $order) => (new OrderResource($order))->resolve($request))
                ->values(),
            'low_stock_variants' => (clone $lowStock)
                ->with('product:id,name')
                ->orderBy('stock_quantity')
                ->limit(5)
                ->get(['id', 'product_id', 'sku', 'size', 'color', 'stock_quantity'])
                ->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'product_name' => $variant->product?->name ?? 'Product',
                    'sku' => $variant->sku,
                    'size' => $variant->size,
                    'color' => $variant->color,
                    'stock_quantity' => (int) $variant->stock_quantity,
                ])
                ->values(),
        ]);
    }
}
