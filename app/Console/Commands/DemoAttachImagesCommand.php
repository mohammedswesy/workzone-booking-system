<?php

namespace App\Console\Commands;

use App\Enums\VenueStatus;
use App\Models\Venue;
use App\Services\Venues\DemoVenueImageService;
use Illuminate\Console\Command;

class DemoAttachImagesCommand extends Command
{
    protected $signature = 'demo:attach-images
        {--dry-run : List actions without writing files or rows}
        {--venue= : Limit to a venue slug}
        {--force-count=3 : Ensure each venue has at least N images (only adds missing)}';

    protected $description = 'Attach deterministic demo gallery images to draft/published venues missing coverage';

    public function handle(DemoVenueImageService $demo): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $slug = $this->option('venue');
        $target = max(1, (int) $this->option('force-count'));

        $query = Venue::query()
            ->with(['units', 'images'])
            ->whereIn('status', [VenueStatus::Published, VenueStatus::Draft]);

        if (is_string($slug) && $slug !== '') {
            $query->where('slug', $slug);
        }

        $venues = $query->orderBy('id')->get();
        if ($venues->isEmpty()) {
            $this->warn('No matching venues.');

            return self::SUCCESS;
        }

        $attachedTotal = 0;
        $skipped = 0;

        foreach ($venues as $venue) {
            $result = $demo->attachForVenue($venue, $target, $dryRun);
            if ($result['skipped']) {
                $skipped++;
                $this->line(sprintf(
                    '[skip] %s (%s) — %s',
                    $venue->slug,
                    $venue->status->value,
                    $result['reason'] ?? 'skipped'
                ));

                continue;
            }

            $attachedTotal += $result['attached'];
            $this->info(sprintf(
                '[%s] %s — attach %d: %s',
                $dryRun ? 'dry-run' : 'ok',
                $venue->slug,
                $result['attached'],
                implode(', ', $result['files'])
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. venues=%d attached=%d skipped=%d%s',
            $venues->count(),
            $attachedTotal,
            $skipped,
            $dryRun ? ' (dry-run)' : ''
        ));

        return self::SUCCESS;
    }
}
