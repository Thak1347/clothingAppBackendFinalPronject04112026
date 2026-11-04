<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // RESTRICT: cannot delete a user who still has orders.
            // Admins must explicitly anonymise/soft-delete instead.
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // NULLABLE: order survives if customer deletes the saved address.
            // SET NULL preserves the row; the snapshot below keeps delivery data intact.
            $table->foreignId('address_id')
                ->nullable()
                ->constrained('addresses')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('order_number', 40)->unique();

            // --- Immutable delivery snapshot ---
            // Preserved even if the address is edited/deleted later.
            $table->string('ship_recipient_name', 120);
            $table->string('ship_phone', 30);
            $table->string('ship_address_line', 255);
            $table->string('ship_province', 120);
            $table->decimal('ship_latitude', 10, 7)->nullable();
            $table->decimal('ship_longitude', 10, 7)->nullable();

            // --- Monetary totals (smallest currency unit) ---
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('delivery_fee_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor');

            $table->char('currency', 3);        // ISO-4217, e.g. "USD", "KHR"

            $table->string('fulfillment_status', 30)
                ->default('pending')
                ->index();

            $table->string('payment_status', 30)
                ->default('pending')
                ->index();

            $table->timestamps();

            // Query: "my orders, newest first".
            $table->index(['user_id', 'created_at']);
            // Query: admin dashboard by status.
            $table->index(['fulfillment_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
