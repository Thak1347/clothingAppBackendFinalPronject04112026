<?php

namespace Tests\Feature\Checkout;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateOrderWithAvailableStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_order_when_variant_has_stock(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->forUser($user)->default()->create();
        $product = Product::factory()->create(['price_minor' => 2500]);
        $variant = ProductVariant::factory()
            ->for($product)
            ->inStock(26)
            ->create(['size' => 'S', 'color' => 'Black']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/orders', [
            'address_id' => $address->id,
            'idempotency_key' => 'order-stock-test-001',
            'payment_method' => 'aba_pay',
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.items.0.product_name', $product->name)
            ->assertJsonPath('data.items.0.unit_price_minor', 2500)
            ->assertJsonPath('data.payment.amount_minor', 2500);

        $this->assertSame(25, $variant->fresh()->stock_quantity);
        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
            'total_minor' => 2500,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $response->json('data.id'),
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'line_total_minor' => 2500,
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $response->json('data.id'),
            'amount_minor' => 2500,
        ]);
    }
}
