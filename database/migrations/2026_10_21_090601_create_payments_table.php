<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('provider', 40);            // "khqr" | ...
            $table->string('payment_reference', 80)->unique();  // internal
            $table->string('provider_txn_ref', 120)
                ->nullable()
                ->unique();

            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);

            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();

            // Safe provider metadata (e.g. QR hash, masked card, provider id).
            // Never store raw PAN, CVV, or full provider payloads here.
            $table->json('provider_metadata')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
