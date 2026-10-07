<?php

namespace App\Console\Commands;

use App\Models\VenueImage;
use App\Models\WorkspaceImage;
use App\Services\Media\ImageDerivativeService;
use Illuminate\Console\Command;

class MediaRegenerateDerivativesCommand extends Command
{
    protected $signature = 'media:regenerate-derivatives
        {--dry-run : List paths without writing}';

    protected $description = 'Regenerate card/gallery WebP derivatives for venue and workspace images (originals unchanged)';

    public function handle(ImageDerivativeService $derivatives): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $paths = collect()
            ->merge(VenueImage::query()->pluck('path'))
            ->merge(WorkspaceImage::query()->pluck('path'))
            ->filter()
            ->unique()
            ->values();

        $done = 0;
        foreach ($paths as $path) {
            if ($dryRun) {
                $this->line('[dry-run] '.$path);

                continue;
            }
            $derivatives->ensureForPath((string) $path);
            $done++;
        }

        $this->info($dryRun
            ? "Dry-run: {$paths->count()} path(s)."
            : "Regenerated derivatives for {$done} path(s)."
        );

        return self::SUCCESS;
    }
}
