<?php

/**
 * FLAG BEFORE MYSQL: adds payments.platform_payment_method_id and payments.transfer_reference
 * (user-entered ref, unique when set). Keeps existing payments.reference for internal IDs.
 * Reversible via down().
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('platform_payment_method_id')
                ->nullable()
                ->after('booking_id')
                ->constrained('platform_payment_methods')
                ->nullOnDelete();
            $table->string('transfer_reference')->nullable()->after('reference');
            $table->unique('transfer_reference');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['transfer_reference']);
            $table->dropConstrainedForeignId('platform_payment_method_id');
            $table->dropColumn('transfer_reference');
        });
    }
};
