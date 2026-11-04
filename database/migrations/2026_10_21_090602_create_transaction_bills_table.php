<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transaction_bills', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id')
                ->constrained('payments')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('bill_number', 80)->unique();
            $table->string('receipt_reference', 120)->unique();
            $table->timestamp('issued_at');

            // Optional: full frozen receipt payload for re-printing.
            $table->json('payload')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_bills');
    }
};
