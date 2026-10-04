<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->text('payment_instructions')->nullable()->after('featured');
            $table->json('payment_methods')->nullable()->after('payment_instructions');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('rejection_reason', 500)->nullable()->after('proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['payment_instructions', 'payment_methods']);
        });
    }
};
