<?php
use App\Models\Address;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TransactionBill;
use App\Models\User;

it('links user -> addresses -> orders', function () {
    [$user, $address] = aCustomerWithAddress();
    $order = Order::factory()->forUser($user)->create(['address_id' => $address->id]);

    expect($user->addresses)->toHaveCount(1)
        ->and($user->orders->pluck('id')->all())->toContain($order->id)
        ->and($address->user->id)->toBe($user->id)
        ->and($order->address->id)->toBe($address->id);
});

it('links category -> products -> variants', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->forCategory($category)->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    expect($category->products->pluck('id')->all())->toContain($product->id)
        ->and($product->category->id)->toBe($category->id)
        ->and($product->variants->pluck('id')->all())->toContain($variant->id)
        ->and($variant->product->id)->toBe($product->id);
});

it('links order -> items -> variant', function () {
    $variant = ProductVariant::factory()->create();
    $order = Order::factory()->create();
    $item = OrderItem::factory()->forOrder($order)->create([
        'product_variant_id' => $variant->id,
    ]);

    expect($order->items->pluck('id')->all())->toContain($item->id)
        ->and($item->order->id)->toBe($order->id)
        ->and($item->variant->id)->toBe($variant->id);
});

it('links payment -> transaction_bill and order -> payment', function () {
    $order = Order::factory()->create();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'amount_minor' => $order->total_minor,
        'currency' => $order->currency,
    ]);
    $bill = TransactionBill::factory()->create(['payment_id' => $payment->id]);

    expect($order->payments->pluck('id')->all())->toContain($payment->id)
        ->and($payment->transactionBill->id)->toBe($bill->id)
        ->and($bill->payment->id)->toBe($payment->id);
});

it('links notification to user and order', function () {
    $order = Order::factory()->create();
    $note = Notification::factory()->create([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
    ]);

    expect($note->user->id)->toBe($order->user_id)
        ->and($note->order->id)->toBe($order->id)
        ->and($order->notifications->pluck('id')->all())->toContain($note->id);
});
