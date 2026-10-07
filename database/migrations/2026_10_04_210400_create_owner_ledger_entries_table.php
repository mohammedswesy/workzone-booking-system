<?php

/**
 * FLAG BEFORE MYSQL: creates owner_ledger_entries (immutable owner money ledger).
 * Types: earning|commission|refund|payout|adjustment. Reversible via down().
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('payout_id')->nullable()->index();
            $table->string('type', 32);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 32)->default('posted');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['owner_id', 'type']);
            $table->index(['owner_id', 'created_at']);
            $table->index(['booking_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_ledger_entries');
    }
};
