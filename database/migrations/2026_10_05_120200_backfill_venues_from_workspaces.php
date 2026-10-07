<?php

use App\Services\Venues\VenueMigrationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * FLAG (MySQL): data backfill — one venue per existing workspace; unit keeps same id.
 * Stops with a clear report if preflight fails (null owners, orphan bookings, slug collisions).
 * Does not delete rows. Does not drop columns.
 * Real rollback = restore backup; down() detaches units and deletes backfilled venues best-effort.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venues') || ! Schema::hasColumn('workspaces', 'venue_id')) {
            throw new \RuntimeException('Run create_venues_tables and add_venue_fields migrations first.');
        }

        /** @var VenueMigrationService $service */
        $service = app(VenueMigrationService::class);
        $service->backfill();
    }

    public function down(): void
    {
        // Best-effort only. Prefer restoring a DB backup.
        if (! Schema::hasTable('venues')) {
            return;
        }

        \Illuminate\Support\Facades\DB::table('workspaces')->update([
            'venue_id' => null,
            'type' => null,
        ]);

        \Illuminate\Support\Facades\DB::table('venue_slug_redirects')->delete();
        \Illuminate\Support\Facades\DB::table('amenity_venue')->delete();
        \Illuminate\Support\Facades\DB::table('venue_images')->delete();
        \Illuminate\Support\Facades\DB::table('venues')->delete();
    }
};
