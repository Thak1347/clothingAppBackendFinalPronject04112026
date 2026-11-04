<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // SET NULL: keeps audit trail even if order removed by retention job.
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('channel', 20);          // telegram | push | email
            $table->string('type', 60);             // order_created | payment_paid | shipped ...
            $table->string('title', 160);
            $table->text('body');

            // Frozen snapshot for auditability — never mutated after send.
            $table->json('payload')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['order_id', 'type']);
            $table->index(['channel', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
