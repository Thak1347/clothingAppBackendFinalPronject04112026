<?php
namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->paragraph(),
            // Bundled Flutter asset key (NOT a URL).
            'asset_key' => 'asset_' . fake()->unique()->numberBetween(1, 99999),
            // Price in smallest currency unit (e.g. cents).
            'price_minor' => fake()->numberBetween(1000, 500000),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn() => ['is_active' => false]);
    }

    public function forCategory(Category $category): static
    {
        return $this->state(fn() => ['category_id' => $category->id]);
    }
}
