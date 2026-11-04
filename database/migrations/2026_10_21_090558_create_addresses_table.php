<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            // CASCADE is fine: deleting a user deletes their address book.
            // Historical orders are safe because order snapshots the address.
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('label', 60)->nullable();
            $table->string('recipient_name', 120);
            $table->string('phone', 30);
            $table->string('address_line', 255);
            $table->string('province', 120);        // "province or city"

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // Fast lookup: "user's addresses" and "user's default address".
            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
