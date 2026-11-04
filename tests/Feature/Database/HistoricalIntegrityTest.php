<?php
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;

it('preserves delivery snapshot when the saved address is deleted', function () {
    [$user, $address] = aCustomerWithAddress();

    $order = Order::factory()->forUser($user)->create([
        'address_id' => $address->id,
        'ship_recipient_name' => 'Original Name',
        'ship_phone' => '012345678',
        'ship_address_line' => '1 Original Street',
        'ship_province' => 'Phnom Penh',
    ]);

    $address->delete();
    $fresh = $order->fresh();

    expect($fresh->address_id)->toBeNull()
        ->and($fresh->ship_recipient_name)->toBe('Original Name')
        ->and($fresh->ship_phone)->toBe('012345678')
        ->and($fresh->ship_address_line)->toBe('1 Original Street')
        ->and($fresh->ship_province)->toBe('Phnom Penh');
});

it('preserves item snapshots when a product variant is deleted', function () {
    $variant = ProductVariant::factory()->create([
        'size' => 'M',
        'color' => 'Red',
    ]);

    $order = Order::factory()->create();

    $item = OrderItem::factory()->forOrder($order)->create([
        'product_variant_id' => $variant->id,
        'product_name_snapshot' => 'Classic Tee',
        'asset_key_snapshot' => 'tee_001',
        'size_snapshot' => 'M',
        'color_snapshot' => 'Red',
        'quantity' => 2,
        'unit_price_minor' => 1500,
        'line_total_minor' => 3000,
    ]);

    $variant->delete();
    $fresh = $item->fresh();

    expect($fresh->product_variant_id)->toBeNull()
        ->and($fresh->product_name_snapshot)->toBe('Classic Tee')
        ->and($fresh->asset_key_snapshot)->toBe('tee_001')
        ->and($fresh->size_snapshot)->toBe('M')
        ->and($fresh->color_snapshot)->toBe('Red')
        ->and($fresh->quantity)->toBe(2)
        ->and($fresh->unit_price_minor)->toBe(1500)
        ->and($fresh->line_total_minor)->toBe(3000);
});

it('blocks deleting a category that still has products', function () {
    $product = \App\Models\Product::factory()->create();
    $category = $product->category;

    expect(fn() => $category->delete())
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('blocks deleting a user who has orders', function () {
    $user = User::factory()->create();
    Order::factory()->forUser($user)->create();

    expect(fn() => $user->delete())
        ->toThrow(\Illuminate\Database\QueryException::class);
});
