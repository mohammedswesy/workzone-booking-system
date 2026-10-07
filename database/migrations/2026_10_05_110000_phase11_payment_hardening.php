<?php

/**
 * FLAG BEFORE MYSQL: Phase 11 payment hardening schema.
 * - Drop global unique on payments.transfer_reference; add composite
 *   (platform_payment_method_id, transfer_reference) for per-method uniqueness.
 * - Add proof hash / attempt / amount confirmation columns on payments.
 * - Create append-only audit_logs.
 * - Add admin 2FA columns on users.
 * Reversible via down(). Does NOT delete existing invalid transfer_reference rows.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['transfer_reference']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique(
                ['platform_payment_method_id', 'transfer_reference'],
                'payments_method_transfer_reference_unique'
            );
            $table->string('proof_sha256', 64)->nullable()->after('proof_path');
            $table->unsignedTinyInteger('proof_upload_attempts')->default(0)->after('proof_sha256');
            $table->decimal('received_amount', 12, 2)->nullable()->after('amount');
            $table->string('amount_disposition', 20)->nullable()->after('received_amount');
            $table->text('amount_note')->nullable()->after('amount_disposition');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 20)->nullable();
            $table->string('action', 80);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('remember_token');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->timestamp('last_seen_at')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'last_seen_at',
            ]);
        });

        Schema::dropIfExists('audit_logs');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_method_transfer_reference_unique');
            $table->dropColumn([
                'proof_sha256',
                'proof_upload_attempts',
                'received_amount',
                'amount_disposition',
                'amount_note',
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('transfer_reference');
        });
    }
};
