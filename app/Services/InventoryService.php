<?php
namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /** Reserve (decrement) stock atomically. Throws if insufficient. */
    public function reserve(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                $variant = ProductVariant::lockForUpdate()
                    ->findOrFail($item['product_variant_id']);

                if ($variant->stock_quantity < $item['quantity']) {
                    abort(422, "Insufficient stock for {$variant->size}/{$variant->color}.");
                }
                $variant->decrement('stock_quantity', $item['quantity']);
            }
        });
    }

    public function release(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                ProductVariant::lockForUpdate()->findOrFail($item['product_variant_id'])
                    ->increment('stock_quantity', (int) $item['quantity']);
            }
        });
    }
}
