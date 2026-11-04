<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // RESTRICT: cannot delete a category that still has products.
            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Asset identifier for bundled Flutter asset (e.g. "tee_001").
            // NOT a URL. The backend never generates or stores image URLs.
            $table->string('asset_key', 100);

            // Price in smallest currency unit (e.g. cents).
            $table->unsignedBigInteger('price_minor');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Catalog query index: active products by category.
            $table->index(['category_id', 'is_active']);
            // Catalog query index: active products sorted by price.
            $table->index(['is_active', 'price_minor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
