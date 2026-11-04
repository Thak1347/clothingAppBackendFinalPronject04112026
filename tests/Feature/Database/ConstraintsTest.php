<?php
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('rejects a product whose category_id does not exist', function () {
    expect(fn() => Product::factory()->create(['category_id' => 999999]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('cascades variant deletion when a product is deleted', function () {
    $product = Product::factory()->create();
    $variants = ProductVariant::factory()->count(3)->sequence(
        ['size' => 'S', 'color' => 'Black'],
        ['size' => 'M', 'color' => 'Blue'],
        ['size' => 'L', 'color' => 'Red'],
    )->create(['product_id' => $product->id]);

    $ids = $variants->pluck('id')->all();
    $product->delete();

    expect(ProductVariant::whereIn('id', $ids)->count())->toBe(0);
});

it('cascades order item deletion when an order is deleted', function () {
    $order = Order::factory()->create();
    $order->items()->createMany(
        \App\Models\OrderItem::factory()->count(2)->make()->toArray()
    );

    expect($order->items()->count())->toBe(2);
    $order->delete();

    expect(\App\Models\OrderItem::where('order_id', $order->id)->count())->toBe(0);
});

it('cascades payment deletion when an order is deleted', function () {
    $order = Order::factory()->create();
    $payment = Payment::factory()->create(['order_id' => $order->id]);

    $order->delete();
    expect(Payment::find($payment->id))->toBeNull();
});

it('prevents duplicate (product, size, color) variant combination', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'size' => 'M',
        'color' => 'Red',
    ]);

    expect(fn() => ProductVariant::factory()->create([
        'product_id' => $product->id,
        'size' => 'M',
        'color' => 'Red',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('preserves a transaction bill row when its payment is deleted only if we detach', function () {
    // By design, transaction_bills cascade with payments.
    // This test documents the intended behaviour.
    $order = Order::factory()->create();
    $payment = Payment::factory()->create(['order_id' => $order->id]);
    $bill = \App\Models\TransactionBill::factory()->create(['payment_id' => $payment->id]);

    $payment->delete();
    expect(\App\Models\TransactionBill::find($bill->id))->toBeNull();
});
