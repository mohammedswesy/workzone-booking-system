<?php

namespace App\Services\Venues;

use App\Enums\BookingMode;
use App\Enums\VenueStatus;
use App\Enums\WorkspaceStatus;
use App\Enums\WorkspaceType;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Venue;
use App\Models\VenueImage;
use App\Models\Workspace;
use App\Models\WorkspaceImage;
use App\Support\PublicStorageUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Read-only checks + backfill for Venue = building / Workspace = unit split.
 */
class VenueMigrationService
{
    /**
     * @return array{ok: bool, errors: list<string>, counts: array<string, int|string>}
     */
    public function preflight(): array
    {
        $errors = [];

        $nullOwners = Workspace::query()->whereNull('owner_id')->count();
        if ($nullOwners > 0) {
            $errors[] = "{$nullOwners} workspace(s) have owner_id NULL — refuse to guess owners.";
        }

        $orphanBookings = Booking::query()
            ->whereDoesntHave('workspace')
            ->count();
        if ($orphanBookings > 0) {
            $errors[] = "{$orphanBookings} booking(s) reference a missing workspace.";
        }

        $slugDupes = Workspace::query()
            ->select('slug')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('slug')
            ->all();
        if ($slugDupes !== []) {
            $errors[] = 'Duplicate workspace slugs (would collide as venue slugs): '.implode(', ', $slugDupes);
        }

        $pending = Workspace::query()->whereNull('venue_id')->count();
        $withVenue = Workspace::query()->whereNotNull('venue_id')->count();

        $imageRows = WorkspaceImage::query()->count();
        $legacyCovers = Workspace::query()
            ->whereNull('venue_id')
            ->whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->whereDoesntHave('images')
            ->count();

        $amenityLinks = (int) DB::table('amenity_workspace')->count();

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'counts' => [
                'workspaces_total' => Workspace::query()->count(),
                'workspaces_pending_backfill' => $pending,
                'workspaces_already_linked' => $withVenue,
                'venues_to_create' => $pending,
                'units_after' => Workspace::query()->count(),
                'workspace_image_rows' => $imageRows,
                'legacy_image_url_imports' => $legacyCovers,
                'amenity_workspace_links' => $amenityLinks,
                'offers' => Offer::query()->count(),
                'bookings' => Booking::query()->count(),
                'orphan_bookings' => $orphanBookings,
                'null_owner_workspaces' => $nullOwners,
                'duplicate_slugs' => count($slugDupes),
            ],
        ];
    }

    /**
     * @return array{ok: bool, errors: list<string>, counts: array<string, int|float|string>}
     */
    public function verify(): array
    {
        $errors = [];

        $unitsWithoutVenue = Workspace::query()->whereNull('venue_id')->count();
        if ($unitsWithoutVenue > 0) {
            $errors[] = "{$unitsWithoutVenue} unit(s) missing venue_id.";
        }

        $ownerMismatch = Workspace::query()
            ->whereNotNull('venue_id')
            ->whereColumn('workspaces.owner_id', '!=', 'venues.owner_id')
            ->join('venues', 'venues.id', '=', 'workspaces.venue_id')
            ->count();
        if ($ownerMismatch > 0) {
            $errors[] = "{$ownerMismatch} unit(s) owner_id out of sync with venue.";
        }

        $badOffers = Offer::query()
            ->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNull('workspace_id')->whereNull('venue_id');
                })->orWhere(function ($inner) {
                    $inner->whereNotNull('workspace_id')->whereNotNull('venue_id');
                });
            })
            ->count();
        if ($badOffers > 0) {
            $errors[] = "{$badOffers} offer(s) violate XOR workspace_id/venue_id.";
        }

        $totals = $this->moneyTotals();

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'counts' => [
                'venues' => Venue::query()->count(),
                'units' => Workspace::query()->count(),
                'venue_images' => VenueImage::query()->count(),
                'amenity_venue' => (int) DB::table('amenity_venue')->count(),
                'units_without_venue' => $unitsWithoutVenue,
                'owner_mismatches' => $ownerMismatch,
                'bookings' => Booking::query()->count(),
                'payments_sum' => $totals['payments_sum'],
                'ledger_sum' => $totals['ledger_sum'],
                'ledger_count' => $totals['ledger_count'],
            ],
        ];
    }

    /**
     * @return array{payments_sum: string, ledger_sum: string, ledger_count: int, bookings_count: int}
     */
    public function moneyTotals(): array
    {
        return [
            'payments_sum' => (string) (DB::table('payments')->sum('amount') ?? '0'),
            'ledger_sum' => (string) (DB::table('owner_ledger_entries')->sum('amount') ?? '0'),
            'ledger_count' => (int) DB::table('owner_ledger_entries')->count(),
            'bookings_count' => (int) DB::table('bookings')->count(),
        ];
    }

    /**
     * @return array{created_venues: int, linked_units: int}
     */
    public function backfill(): array
    {
        $pre = $this->preflight();
        if (! $pre['ok']) {
            throw new RuntimeException("Venues backfill refused:\n- ".implode("\n- ", $pre['errors']));
        }

        $created = 0;
        $linked = 0;

        Workspace::query()
            ->whereNull('venue_id')
            ->with(['images', 'amenities', 'place'])
            ->orderBy('id')
            ->chunkById(50, function (Collection $chunk) use (&$created, &$linked) {
                foreach ($chunk as $workspace) {
                    DB::transaction(function () use ($workspace, &$created, &$linked) {
                        $this->backfillOne($workspace);
                        $created++;
                        $linked++;
                    });
                }
            });

        return compact('created', 'linked');
    }

    private function backfillOne(Workspace $workspace): void
    {
        /** @var Workspace $workspace */
        $workspace->refresh();
        if ($workspace->venue_id) {
            return;
        }

        if ($workspace->owner_id === null) {
            throw new RuntimeException("Workspace #{$workspace->id} has null owner_id.");
        }

        $slug = Venue::ensureSlugHasNonDigit((string) ($workspace->slug ?: Str::slug($workspace->name) ?: 'venue-'.$workspace->id));
        $slug = $this->allocateVenueSlug($slug, null);

        $status = $workspace->status instanceof WorkspaceStatus
            ? $workspace->status->value
            : (string) $workspace->status;

        $venue = Venue::query()->create([
            'owner_id' => $workspace->owner_id,
            'name' => $workspace->name,
            'slug' => $slug,
            'description' => $workspace->description,
            'location_id' => $workspace->location_id,
            'address' => $workspace->place?->address ?: $workspace->location,
            'status' => in_array($status, VenueStatus::values(), true) ? $status : VenueStatus::Draft->value,
            'featured' => (bool) $workspace->featured,
        ]);

        $mode = $workspace->booking_mode instanceof BookingMode
            ? $workspace->booking_mode
            : BookingMode::tryFrom((string) $workspace->booking_mode) ?? BookingMode::Whole;

        $workspace->forceFill([
            'venue_id' => $venue->id,
            'type' => WorkspaceType::fromBookingMode($mode)->value,
            'owner_id' => $venue->owner_id,
        ])->save();

        $this->copyImages($workspace, $venue);
        $this->copyAmenities($workspace, $venue);
    }

    private function allocateVenueSlug(string $base, ?int $ignoreVenueId): string
    {
        $slug = $base;
        $i = 2;
        while (
            Venue::query()
                ->when($ignoreVenueId, fn ($q) => $q->whereKeyNot($ignoreVenueId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function copyImages(Workspace $workspace, Venue $venue): void
    {
        $sort = 0;
        foreach ($workspace->images as $image) {
            VenueImage::query()->create([
                'venue_id' => $venue->id,
                'path' => $image->path,
                'is_primary' => (bool) $image->is_primary,
                'sort_order' => $image->sort_order ?? $sort,
            ]);
            $sort++;
        }

        if ($workspace->images->isEmpty() && filled($workspace->getRawOriginal('image_url') ?? $workspace->attributes['image_url'] ?? null)) {
            $raw = (string) ($workspace->getAttributes()['image_url'] ?? '');
            $normalized = PublicStorageUrl::fromPath($raw);
            $path = $this->storageRelativePath($normalized ?? $raw);
            if ($path !== null) {
                VenueImage::query()->create([
                    'venue_id' => $venue->id,
                    'path' => $path,
                    'is_primary' => true,
                    'sort_order' => 0,
                ]);
            }
        }
    }

    private function storageRelativePath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }
        if (str_starts_with($path, 'workspaces/') || str_starts_with($path, 'venues/')) {
            return $path;
        }
        if (preg_match('#/storage/(workspaces/.+|venues/.+)$#', $path, $m)) {
            return $m[1];
        }
        if (str_starts_with($path, '/storage/')) {
            return ltrim(substr($path, strlen('/storage/')), '/');
        }

        return null;
    }

    private function copyAmenities(Workspace $workspace, Venue $venue): void
    {
        $ids = $workspace->amenities->pluck('id')->all();
        if ($ids !== []) {
            $venue->amenities()->syncWithoutDetaching($ids);
        }
    }
}
