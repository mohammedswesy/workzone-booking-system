<?php

namespace App\Console\Commands;

use App\Services\Venues\VenueMigrationService;
use Illuminate\Console\Command;

class VenuesVerifyCommand extends Command
{
    protected $signature = 'venues:verify {--json : Machine-readable JSON}';

    protected $description = 'Post-migration verify: every unit has a venue, owner sync, money totals';

    public function handle(VenueMigrationService $migration): int
    {
        $result = $migration->verify();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Venues verify');
        foreach ($result['counts'] as $key => $value) {
            $this->line(sprintf('  %-32s %s', $key, $value));
        }

        if (! $result['ok']) {
            $this->newLine();
            $this->error('VERIFY FAILED:');
            foreach ($result['errors'] as $error) {
                $this->line('  - '.$error);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('OK — venues structure looks consistent.');

        return self::SUCCESS;
    }
}
