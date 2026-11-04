<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method', 30)->nullable()->index();
            $table->string('cod_customer_name', 120)->nullable();
            $table->string('cod_phone', 30)->nullable();
            $table->string('cod_email', 255)->nullable();
            $table->string('cod_telegram', 120)->nullable();
            $table->string('cod_address', 500)->nullable();
            $table->string('cod_home_photo_path')->nullable();
            $table->string('cod_verification_status', 20)->nullable()->index();
            $table->timestamp('cod_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_method']);
            $table->dropIndex(['cod_verification_status']);
            $table->dropColumn(['payment_method', 'cod_customer_name', 'cod_phone', 'cod_email', 'cod_telegram', 'cod_address', 'cod_home_photo_path', 'cod_verification_status', 'cod_verified_at']);
        });
    }
};
