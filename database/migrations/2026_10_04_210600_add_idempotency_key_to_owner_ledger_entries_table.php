<?php

/**
 * FLAG BEFORE MYSQL: adds unique idempotency_key on owner_ledger_entries so
 * earning/commission/refund/payout posts cannot duplicate under races.
 * Reversible via down().
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_ledger_entries', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('id');
            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('owner_ledger_entries', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
