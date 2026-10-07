<?php

/**
 * FLAG BEFORE MYSQL: creates platform_payment_methods (admin-managed pay-to-platform accounts).
 * QR images live on the public disk under platform-payment-qr/. Reversible via down().
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('label');
            $table->string('account_holder')->nullable();
            $table->string('account_identifier')->nullable();
            $table->string('qr_path')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payment_methods');
    }
};
