<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Category;
use App\Models\Product;
use Tests\TestCase;

class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_v1_catalog_routes_are_reachable(): void
    {
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonStructure(['data']);
        $this->getJson('/api/v1/products?per_page=5')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_customer_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/orders')->assertUnauthorized();
        $this->getJson('/api/v1/addresses')->assertUnauthorized();
    }

    public function test_public_catalog_only_exposes_active_products(): void
    {
        $activeProduct = Product::factory()->create();
        $inactiveProduct = Product::factory()->inactive()->create();
        $inactiveCategory = Category::factory()->inactive()->create();
        $productInInactiveCategory = Product::factory()->create([
            'category_id' => $inactiveCategory->id,
        ]);

        $products = $this->getJson('/api/v1/products')->assertOk()->json('data');
        $this->assertSame([$activeProduct->id], array_column($products, 'id'));

        $this->getJson('/api/v1/products/' . $inactiveProduct->id)->assertNotFound();
        $this->getJson('/api/v1/products/' . $productInInactiveCategory->id)->assertNotFound();
        $this->getJson('/api/v1/products/' . $activeProduct->id)->assertOk();

        $categories = $this->getJson('/api/v1/categories')->assertOk()->json('data');
        $this->assertNotContains($inactiveCategory->id, array_column($categories, 'id'));
    }
}
