<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Move any leftover phase-4 public payment proofs onto the private local disk.
 * Paths in the payments table already use payment-proofs/... and do not need renaming.
 */
return new class extends Migration
{
    public function up(): void
    {
        $public = Storage::disk('public');
        $count = 0;

        if ($public->exists('payment-proofs')) {
            $count = count($public->allFiles('payment-proofs'));
        }

        if ($count === 0) {
            Log::info('payment_proofs_private_migrate: none found on public disk');

            return;
        }

        Artisan::call('payments:migrate-proofs-to-private');
        Log::info("payment_proofs_private_migrate: found and processed {$count} file(s)");
    }

    public function down(): void
    {
        // Do not move private proofs back to a public disk.
    }
};
