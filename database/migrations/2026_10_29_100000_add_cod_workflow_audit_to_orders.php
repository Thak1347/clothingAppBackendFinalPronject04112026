<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('cod_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cod_reviewed_at')->nullable();
            $table->timestamp('cod_terms_accepted_at')->nullable();
            $table->string('cod_review_reason', 1000)->nullable();
            $table->unsignedBigInteger('cod_balance_paid_minor')->default(0);
            $table->unsignedBigInteger('refunded_minor')->default(0);
            $table->unsignedBigInteger('refund_pending_minor')->default(0);
            $table->timestamp('cod_balance_paid_at')->nullable();
            $table->foreignId('cod_balance_received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inventory_released_at')->nullable();
            $table->string('cancellation_requested_from', 40)->nullable();
            $table->string('cancellation_review_reason', 1000)->nullable();
            $table->timestamp('cod_home_photo_delete_at')->nullable()->index();
        });

        Schema::create('order_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 60)->index();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_activities');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cod_reviewed_by');
            $table->dropConstrainedForeignId('cod_balance_received_by');
            $table->dropIndex(['cod_home_photo_delete_at']);
            $table->dropColumn(['cod_reviewed_at', 'cod_terms_accepted_at', 'cod_review_reason', 'cod_balance_paid_minor', 'refunded_minor', 'refund_pending_minor', 'cod_balance_paid_at', 'inventory_released_at', 'cod_home_photo_delete_at', 'cancellation_requested_from', 'cancellation_review_reason']);
        });
    }
};
