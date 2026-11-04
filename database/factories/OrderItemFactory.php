<?php
namespace Database\Factories;

use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 5);
        $unitMinor = fake()->numberBetween(1000, 50000);

        return [
            'order_id' => Order::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'product_name_snapshot' => fake()->words(2, true),
            'asset_key_snapshot' => 'asset_' . fake()->numberBetween(1, 999),
            'size_snapshot' => fake()->randomElement(['S', 'M', 'L']),
            'color_snapshot' => fake()->randomElement(['Black', 'White']),
            'quantity' => $qty,
            'unit_price_minor' => $unitMinor,
            'line_total_minor' => $qty * $unitMinor,
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn() => ['order_id' => $order->id]);
    }
}
