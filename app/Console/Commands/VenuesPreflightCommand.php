<?php

namespace App\Console\Commands;

use App\Services\Venues\VenueMigrationService;
use Illuminate\Console\Command;

class VenuesPreflightCommand extends Command
{
    protected $signature = 'venues:preflight {--json : Machine-readable JSON}';

    protected $description = 'Read-only preflight for venues backfill (null owners, orphans, slug collisions, counts)';

    public function handle(VenueMigrationService $migration): int
    {
        $result = $migration->preflight();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Venues preflight (read-only)');
        foreach ($result['counts'] as $key => $value) {
            $this->line(sprintf('  %-32s %s', $key, $value));
        }

        if (! $result['ok']) {
            $this->newLine();
            $this->error('BLOCKED — fix before running the backfill migration:');
            foreach ($result['errors'] as $error) {
                $this->line('  - '.$error);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('OK — safe to run the venues backfill migration on a data copy.');

        return self::SUCCESS;
    }
}
