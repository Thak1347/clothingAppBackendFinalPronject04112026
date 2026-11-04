<?php
namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVariantRequest;
use App\Http\Requests\Admin\UpdateVariantRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;

class ProductVariantController extends Controller
{
    public function index(Product $product)
    {
        return ProductVariantResource::collection(
            $product->variants()->paginate(50)
        );
    }

    public function store(StoreVariantRequest $request, Product $product)
    {
        $variant = $product->variants()->create($request->validated());

        return (new ProductVariantResource($variant))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateVariantRequest $request, Product $product, ProductVariant $variant)
    {
        abort_unless($variant->product_id === $product->id, 404);
        $variant->update($request->validated());

        return new ProductVariantResource($variant->fresh());
    }

    public function destroy(Product $product, ProductVariant $variant)
    {
        abort_unless($variant->product_id === $product->id, 404);
        $variant->delete();

        return response()->noContent();
    }
}
