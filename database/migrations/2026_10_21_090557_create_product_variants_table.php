<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('sku', 100)->unique();
            $table->string('size', 30);
            $table->string('color', 50);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->timestamps();

            // Prevent duplicate (product, size, color) combos.
            $table->unique(['product_id', 'size', 'color'], 'variants_unique_combo');
            $table->index('stock_quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
