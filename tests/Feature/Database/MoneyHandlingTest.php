<?php
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

it('stores product price as integer minor units', function () {
    $product = Product::factory()->create(['price_minor' => 1999]);

    expect($product->fresh()->price_minor)->toBeInt()->toBe(1999);
});

it('computes order total using integer math', function () {
    $order = Order::factory()->create([
        'subtotal_minor' => 5000,
        'delivery_fee_minor' => 200,
        'discount_minor' => 500,
        'total_minor' => 4700,
    ]);

    expect($order->fresh()->total_minor)->toBeInt()->toBe(4700);
});

it('stores order item line totals as integers', function () {
    $item = OrderItem::factory()->create([
        'quantity' => 3,
        'unit_price_minor' => 1299,
        'line_total_minor' => 3897,
    ]);

    $fresh = $item->fresh();
    expect($fresh->unit_price_minor)->toBeInt()->toBe(1299)
        ->and($fresh->line_total_minor)->toBeInt()->toBe(3897)
        ->and($fresh->line_total_minor)->toBe($fresh->unit_price_minor * $fresh->quantity);
});
