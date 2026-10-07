<?php

/**
 * FLAG BEFORE MYSQL: adds owner payout profile + optional commission_percent override on users.
 * Existing users: null commission (use platform default), empty payout fields. Reversible via down().
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->nullable()->after('must_change_password');
            $table->string('payout_method', 32)->nullable()->after('commission_percent');
            $table->string('payout_account_holder')->nullable()->after('payout_method');
            $table->string('payout_account_identifier')->nullable()->after('payout_account_holder');
            $table->text('payout_note')->nullable()->after('payout_account_identifier');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'commission_percent',
                'payout_method',
                'payout_account_holder',
                'payout_account_identifier',
                'payout_note',
            ]);
        });
    }
};
