<?php
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TransactionBill;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CustomerUserSeeder;
use Database\Seeders\DemoOrderSeeder;
use Database\Seeders\ProductSeeder;

it('creates exactly one admin after AdminUserSeeder', function () {
    $this->seed(AdminUserSeeder::class);

    expect(User::where('role', 'admin')->count())->toBe(1);
});

it('creates 3 customers each with a default address', function () {
    $this->seed(CustomerUserSeeder::class);

    expect(User::where('role', 'customer')->count())->toBe(3);

    User::where('role', 'customer')->with('addresses')->each(function ($u) {
        expect($u->addresses)->toHaveCount(1)
            ->and($u->addresses->first()->is_default)->toBeTrue();
    });
});

it('seeds 4 categories and 20 products with variants', function () {
    $this->seed([CategorySeeder::class, ProductSeeder::class]);

    expect(Category::count())->toBe(4)
        ->and(Product::count())->toBe(20)
        ->and(ProductVariant::count())->toBe(20 * 6); // 3 sizes x 2 colors

    Product::with('variants')->each(function ($p) {
        expect($p->variants)->toHaveCount(6);
    });
});

it('seeds coherent demo orders with payments and receipts', function () {
    $this->seed([
        CustomerUserSeeder::class,
        CategorySeeder::class,
        ProductSeeder::class,
        DemoOrderSeeder::class,
    ]);

    expect(Order::count())->toBeGreaterThanOrEqual(3);

    Order::with(['items', 'payments'])->each(function ($order) {
        expect($order->items)->not->toBeEmpty()
            ->and($order->payments)->toHaveCount(1)
            ->and($order->total_minor)
            ->toBe($order->subtotal_minor + $order->delivery_fee_minor - $order->discount_minor);

        $payment = $order->payments->first();
        expect($payment->amount_minor)->toBe($order->total_minor)
            ->and($payment->currency)->toBe($order->currency);

        expect(TransactionBill::where('payment_id', $payment->id)->exists())->toBeTrue();
    });
});
