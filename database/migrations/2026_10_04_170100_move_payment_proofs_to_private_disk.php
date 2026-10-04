<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Move legacy public payment proofs into the private local disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');

        if (! $public->exists('payment-proofs')) {
            return;
        }

        foreach ($public->allFiles('payment-proofs') as $path) {
            if ($private->exists($path)) {
                continue;
            }
            $private->put($path, $public->get($path));
            $public->delete($path);
        }
    }

    public function down(): void
    {
        // Do not move private proofs back to a public disk.
    }
};
