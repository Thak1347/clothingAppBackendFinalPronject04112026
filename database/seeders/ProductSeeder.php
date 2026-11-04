<?php
namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();
        $sizes = ['S', 'M', 'L'];
        $colors = ['Black', 'White'];

        foreach ($categories as $category) {
            for ($i = 1; $i <= 5; $i++) {
                $name = "{$category->name} Model {$i}";

                $product = Product::create([
                    'category_id' => $category->id,
                    'name' => $name,
                    'slug' => Str::slug($name) . '-' . $category->id . '-' . $i,
                    'description' => "Sample description for {$name}.",
                    'asset_key' => strtolower(Str::slug($category->name, '_')) . '_' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'price_minor' => (random_int(20, 80) * 100), // USD cents
                    'is_active' => true,
                ]);

                foreach ($sizes as $size) {
                    foreach ($colors as $color) {
                        ProductVariant::create([
                            'product_id' => $product->id,
                            'sku' => strtoupper(Str::slug($product->slug . '-' . $size . '-' . $color, '-')),
                            'size' => $size,
                            'color' => $color,
                            'stock_quantity' => random_int(5, 40),
                        ]);
                    }
                }
            }
        }
    }
}
