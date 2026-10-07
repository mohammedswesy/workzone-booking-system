<?php

namespace App\Console\Commands;

use App\Services\Venues\DemoVenueImageService;
use Illuminate\Console\Command;

class DemoRemoveImagesCommand extends Command
{
    protected $signature = 'demo:remove-images
        {--venue= : Limit to a venue slug}
        {--dry-run : Count demo rows without deleting}';

    protected $description = 'Delete only demo-marked venue gallery images (and unreferenced files)';

    public function handle(DemoVenueImageService $demo): int
    {
        $slug = $this->option('venue');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $query = \App\Models\VenueImage::query()->where('is_demo', true);
            if (is_string($slug) && $slug !== '') {
                $query->whereHas('venue', fn ($q) => $q->where('slug', $slug));
            }
            $count = $query->count();
            $this->info("Dry-run: would remove {$count} demo image row(s).");

            return self::SUCCESS;
        }

        $result = $demo->removeAllDemoImages(is_string($slug) && $slug !== '' ? $slug : null);
        $this->info(sprintf(
            'Removed %d demo row(s); deleted %d unreferenced file(s).',
            $result['rows'],
            $result['files_deleted']
        ));

        return self::SUCCESS;
    }
}
