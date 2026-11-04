<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            // CASCADE: deleting an order removes its items (order = aggregate root).
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // SET NULL: deleting a variant must NOT break historical orders.
            // The snapshot columns below remain authoritative.
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // --- Snapshot of everything the customer bought ---
            $table->string('product_name_snapshot', 200);
            $table->string('asset_key_snapshot', 100);
            $table->string('size_snapshot', 30);
            $table->string('color_snapshot', 50);

            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');   // snapshot at purchase
            $table->unsignedBigInteger('line_total_minor');   // unit_price * quantity

            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
