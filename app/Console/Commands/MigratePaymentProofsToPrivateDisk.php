<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigratePaymentProofsToPrivateDisk extends Command
{
    protected $signature = 'payments:migrate-proofs-to-private
                            {--dry-run : Report what would move without writing}';

    protected $description = 'Move legacy public-disk payment proofs to the private local disk';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        $dryRun = (bool) $this->option('dry-run');

        $paths = [];

        if ($public->exists('payment-proofs')) {
            $paths = $public->allFiles('payment-proofs');
        }

        // Also catch DB rows whose files still only exist on public.
        $dbPaths = Payment::query()
            ->whereNotNull('proof_path')
            ->pluck('proof_path')
            ->filter()
            ->unique()
            ->values();

        foreach ($dbPaths as $path) {
            if ($public->exists($path) && ! in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        $found = count($paths);
        $moved = 0;
        $skipped = 0;

        if ($found === 0) {
            $this->info('No public-disk payment proof files found.');

            return self::SUCCESS;
        }

        $this->info("Found {$found} payment proof file(s) on the public disk.");

        foreach ($paths as $path) {
            if ($private->exists($path)) {
                $skipped++;
                if (! $dryRun) {
                    $public->delete($path);
                }
                $this->line("  skip (already private): {$path}");

                continue;
            }

            if ($dryRun) {
                $this->line("  would move: {$path}");
                $moved++;

                continue;
            }

            $private->put($path, $public->get($path));
            $public->delete($path);
            $moved++;
            $this->line("  moved: {$path}");
        }

        $this->info(($dryRun ? 'Would move' : 'Moved').": {$moved}; already private / cleaned: {$skipped}.");

        return self::SUCCESS;
    }
}
