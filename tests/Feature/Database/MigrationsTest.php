<?php
use Illuminate\Support\Facades\Schema;

it('creates every required table', function () {
    $tables = [
        'users',
        'addresses',
        'categories',
        'products',
        'product_variants',
        'orders',
        'order_items',
        'payments',
        'transaction_bills',
        'notifications',
        'device_tokens',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Missing table: {$table}");
    }
});

it('has expected columns on orders', function () {
    $cols = [
        'id',
        'user_id',
        'address_id',
        'order_number',
        'ship_recipient_name',
        'ship_phone',
        'ship_address_line',
        'ship_province',
        'ship_latitude',
        'ship_longitude',
        'subtotal_minor',
        'delivery_fee_minor',
        'discount_minor',
        'total_minor',
        'currency',
        'fulfillment_status',
        'payment_status',
        'created_at',
        'updated_at',
    ];

    foreach ($cols as $col) {
        expect(Schema::hasColumn('orders', $col))->toBeTrue("Missing column orders.{$col}");
    }
});

it('has expected columns on order_items snapshots', function () {
    foreach ([
        'product_name_snapshot',
        'asset_key_snapshot',
        'size_snapshot',
        'color_snapshot',
        'quantity',
        'unit_price_minor',
        'line_total_minor',
    ] as $col) {
        expect(Schema::hasColumn('order_items', $col))->toBeTrue("Missing order_items.{$col}");
    }
});

it('enforces unique email on users', function () {
    \App\Models\User::factory()->create(['email' => 'dup@example.com']);

    expect(fn() => \App\Models\User::factory()->create(['email' => 'dup@example.com']))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

it('enforces unique sku on product_variants', function () {
    \App\Models\ProductVariant::factory()->create(['sku' => 'DUP-SKU']);

    expect(fn() => \App\Models\ProductVariant::factory()->create(['sku' => 'DUP-SKU']))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});
