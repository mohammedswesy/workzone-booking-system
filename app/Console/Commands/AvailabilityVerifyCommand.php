<?php

namespace App\Console\Commands;

use App\Services\Availability\AvailabilityVerifier;
use Illuminate\Console\Command;

class AvailabilityVerifyCommand extends Command
{
    protected $signature = 'availability:verify {--json : Machine-readable JSON}';

    protected $description = 'Read-only availability health check (hours completeness, overrides, exceptions, paused published venues)';

    public function handle(AvailabilityVerifier $verifier): int
    {
        $result = $verifier->verify();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Availability verify');
        $this->line('  checked_at   '.$result['checked_at']);
        $this->line('  issue_count  '.$result['issue_count']);

        if (! $result['ok']) {
            $this->newLine();
            $this->error('VERIFY FAILED:');
            foreach ($result['issues'] as $issue) {
                $this->line('  - ['.$issue['code'].'] '.$issue['message']);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('OK — availability schedules look consistent.');

        return self::SUCCESS;
    }
}
