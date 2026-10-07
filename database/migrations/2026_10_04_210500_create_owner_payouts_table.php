<?php

/**
 * FLAG BEFORE MYSQL: creates owner_payouts (requested → approved → paid | rejected).
 * Adds FK from owner_ledger_entries.payout_id. Reversible via down().
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount_requested', 12, 2);
            $table->decimal('amount_approved', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('status', 32)->default('requested');
            $table->string('payout_method', 32)->nullable();
            $table->string('payout_account_holder')->nullable();
            $table->string('payout_account_identifier')->nullable();
            $table->string('transfer_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('owner_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('owner_ledger_entries', function (Blueprint $table) {
            $table->foreign('payout_id')
                ->references('id')
                ->on('owner_payouts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('owner_ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['payout_id']);
        });

        Schema::dropIfExists('owner_payouts');
    }
};
