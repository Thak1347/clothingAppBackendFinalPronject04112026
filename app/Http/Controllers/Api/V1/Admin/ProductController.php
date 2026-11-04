<?php
namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Requests\Admin\UpdateProductImagesRequest;
use App\Http\Resources\ProductResource;
use App\Jobs\SendPushNotification;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index()
    {
        return ProductResource::collection(
            Product::with(['category', 'variants', 'images'])
                ->orderBy('name')
                ->paginate(50)
        );
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $images = $data['images'];
        unset($data['images']);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['asset_key'] = $data['asset_key'] ?? Str::slug($data['name'], '_');
        $data['is_active'] = $data['is_active'] ?? true;

        $product = Product::create($data);
        $this->storeImages($product, $images);
        SendPushNotification::dispatch(
            $request->user()->id,
            'Product created',
            "{$product->name} was saved successfully.",
        )->afterCommit();

        return (new ProductResource($product->load(['category', 'variants', 'images'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = $request->validated();

        if (isset($data['name']) && !isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $product->update($data);
        SendPushNotification::dispatch(
            $request->user()->id,
            'Product updated',
            "{$product->name} was saved successfully.",
        )->afterCommit();
        return new ProductResource($product->fresh()->load(['category', 'variants', 'images']));
    }

    public function updateImages(UpdateProductImagesRequest $request, Product $product)
    {
        $data = $request->validated();
        $images = $data['images'] ?? [];
        $removeIds = array_values(array_unique($data['remove_image_ids'] ?? []));

        $imagesToRemove = $product->images()->whereIn('id', $removeIds)->get();
        if ($imagesToRemove->count() !== count($removeIds)) {
            throw ValidationException::withMessages([
                'remove_image_ids' => 'One or more selected images do not belong to this product.',
            ]);
        }
        $remainingCount = $product->images()->count() - $imagesToRemove->count();
        if ($remainingCount + count($images) > 10) {
            throw ValidationException::withMessages([
                'images' => 'A product can have at most 10 images.',
            ]);
        }

        foreach ($imagesToRemove as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }
        $this->storeImages($product, $images);

        return new ProductResource($product->fresh()->load(['category', 'variants', 'images']));
    }

    public function destroy(\Illuminate\Http\Request $request, Product $product)
    {
        $productName = $product->name;
        Storage::disk('public')->delete($product->images()->pluck('path')->all());
        $product->delete();
        SendPushNotification::dispatch(
            $request->user()->id,
            'Product deleted',
            "{$productName} was deleted.",
        )->afterCommit();
        return response()->noContent();
    }

    /** @param array<int, UploadedFile> $files */
    private function storeImages(Product $product, array $files): void
    {
        $sortOrder = (int) ($product->images()->max('sort_order') ?? -1) + 1;
        foreach ($files as $file) {
            $path = $file->store('products/'.$product->id, 'public');
            $product->images()->create([
                'path' => $path,
                'sort_order' => $sortOrder++,
            ]);
        }
    }
}
