<?php

use App\Support\PaymentInstructionsPlaceholder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Choice: data backfill (not listing gate).
 *
 * Public listing never required payment_instructions. This migration fills
 * published workspaces that predate the "required to publish" rule with a
 * neutral placeholder (no fake accounts, numbers, or methods).
 */
return new class extends Migration
{
    public function up(): void
    {
        $placeholder = PaymentInstructionsPlaceholder::EN;

        DB::table('workspaces')
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('payment_instructions')
                    ->orWhere('payment_instructions', '');
            })
            ->update([
                'payment_instructions' => $placeholder,
            ]);
    }

    public function down(): void
    {
        // Irreversible data backfill — intentionally empty.
    }
};
