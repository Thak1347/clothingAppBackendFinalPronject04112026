<?php
namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => 'SKU-' . Str::upper(Str::random(10)),
            'size' => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'color' => fake()->randomElement(['Black', 'White', 'Red', 'Blue']),
            'stock_quantity' => fake()->numberBetween(0, 100),
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn() => ['stock_quantity' => 0]);
    }

    public function inStock(int $qty = 25): static
    {
        return $this->state(fn() => ['stock_quantity' => $qty]);
    }
}
