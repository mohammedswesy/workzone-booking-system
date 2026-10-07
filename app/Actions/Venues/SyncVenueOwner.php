<?php

namespace App\Actions\Venues;

use App\Models\Offer;
use App\Models\User;
use App\Models\Venue;
use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Single place that syncs denormalized workspaces.owner_id (and offer owners)
 * from the venue. Venue = building; Workspace = unit.
 *
 * Historical ledger entries are never rewritten.
 */
class SyncVenueOwner
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Keep units in sync when venue.owner_id is already correct (no audit).
     */
    public function syncUnits(Venue $venue): void
    {
        DB::transaction(function () use ($venue) {
            Workspace::query()
                ->where('venue_id', $venue->id)
                ->where(function ($q) use ($venue) {
                    $q->whereNull('owner_id')->orWhere('owner_id', '!=', $venue->owner_id);
                })
                ->update(['owner_id' => $venue->owner_id]);
        });
    }

    /**
     * Explicit admin transfer when venue has bookings or offers.
     */
    public function transfer(
        Venue $venue,
        User $newOwner,
        User $actor,
        bool $confirmed,
    ): Venue {
        if (! $confirmed) {
            throw new InvalidArgumentException('Owner transfer requires explicit confirmation.');
        }

        if (! $newOwner->isOwner() && ! $newOwner->isAdmin()) {
            throw new InvalidArgumentException('New owner must be an owner (or admin) account.');
        }

        return DB::transaction(function () use ($venue, $newOwner, $actor) {
            $from = $venue->owner_id;
            $venue->owner_id = $newOwner->id;
            $venue->save();

            Workspace::query()
                ->where('venue_id', $venue->id)
                ->update(['owner_id' => $newOwner->id]);

            $unitIds = Workspace::query()->where('venue_id', $venue->id)->pluck('id');

            Offer::query()
                ->where(function ($q) use ($venue, $unitIds) {
                    $q->where('venue_id', $venue->id)
                        ->orWhereIn('workspace_id', $unitIds);
                })
                ->update(['owner_id' => $newOwner->id]);

            $this->audit->log(
                'venue.owner_transferred',
                $actor,
                $venue,
                ['owner_id' => $from],
                ['owner_id' => $newOwner->id],
            );

            return $venue->fresh();
        });
    }
}
