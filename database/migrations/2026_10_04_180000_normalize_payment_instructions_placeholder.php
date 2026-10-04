<?php

use App\Support\PaymentInstructionsPlaceholder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replace legacy / empty payment instructions with the neutral EN placeholder.
 * Does not invent bank accounts, numbers, or payment methods.
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
                    ->orWhere('payment_instructions', '')
                    ->orWhere('payment_instructions', PaymentInstructionsPlaceholder::LEGACY_EN);
            })
            ->update([
                'payment_instructions' => $placeholder,
            ]);

        // Clear invented methods from the first backfill when instructions are still a placeholder.
        $rows = DB::table('workspaces')
            ->where('payment_instructions', $placeholder)
            ->pluck('id');

        foreach ($rows as $id) {
            DB::table('workspaces')->where('id', $id)->update([
                'payment_methods' => json_encode([]),
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible data normalization.
    }
};
