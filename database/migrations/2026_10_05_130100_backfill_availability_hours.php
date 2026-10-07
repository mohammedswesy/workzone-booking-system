<?php

/**
 * FLAG BEFORE MYSQL (phase 13 / Stage A backfill):
 * Copies each unit's opening_time/closing_time into unit_hours (all 7 weekdays)
 * and venue_hours (from first unit), so AvailabilityService matches prior behavior.
 * Sets inherits_venue_hours=false when a unit keeps its own schedule.
 * Idempotent: skips venues that already have venue_hours rows.
 * Writes a report to storage/logs/availability_backfill_report.txt
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venue_hours') || ! Schema::hasTable('unit_hours')) {
            return;
        }

        $now = now();
        $venues = DB::table('venues')->select('id', 'slug', 'timezone')->orderBy('id')->get();
        $backfilled = 0;
        $skipped = [];
        $weekdayRows = array_fill(0, 7, 0);
        $unitHourRows = 0;
        $emptyVenueDefaults = 0;

        foreach ($venues as $venue) {
            $existing = DB::table('venue_hours')->where('venue_id', $venue->id)->count();
            if ($existing > 0) {
                $skipped[] = [
                    'venue_id' => $venue->id,
                    'slug' => $venue->slug,
                    'reason' => "already has {$existing} venue_hours row(s)",
                ];

                continue;
            }

            $units = DB::table('workspaces')
                ->where('venue_id', $venue->id)
                ->orderBy('id')
                ->get(['id', 'opening_time', 'closing_time']);

            if ($units->isEmpty()) {
                for ($d = 0; $d <= 6; $d++) {
                    DB::table('venue_hours')->insert([
                        'venue_id' => $venue->id,
                        'weekday' => $d,
                        'opens_at' => '08:00:00',
                        'closes_at' => '22:00:00',
                        'is_closed' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $weekdayRows[$d]++;
                }
                $backfilled++;
                $emptyVenueDefaults++;

                continue;
            }

            $first = $units->first();
            $venueOpen = $this->normalizeTime($first->opening_time) ?? '08:00:00';
            $venueClose = $this->normalizeTime($first->closing_time) ?? '22:00:00';

            for ($d = 0; $d <= 6; $d++) {
                DB::table('venue_hours')->insert([
                    'venue_id' => $venue->id,
                    'weekday' => $d,
                    'opens_at' => $venueOpen,
                    'closes_at' => $venueClose,
                    'is_closed' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $weekdayRows[$d]++;
            }
            $backfilled++;

            foreach ($units as $unit) {
                $open = $this->normalizeTime($unit->opening_time) ?? $venueOpen;
                $close = $this->normalizeTime($unit->closing_time) ?? $venueClose;
                $matchesVenue = $open === $venueOpen && $close === $venueClose;

                DB::table('workspaces')->where('id', $unit->id)->update([
                    'inherits_venue_hours' => $matchesVenue,
                    'updated_at' => $now,
                ]);

                if ($matchesVenue) {
                    continue;
                }

                if (DB::table('unit_hours')->where('workspace_id', $unit->id)->exists()) {
                    continue;
                }

                for ($d = 0; $d <= 6; $d++) {
                    DB::table('unit_hours')->insert([
                        'workspace_id' => $unit->id,
                        'weekday' => $d,
                        'opens_at' => $open,
                        'closes_at' => $close,
                        'is_closed' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $unitHourRows++;
                }
            }
        }

        $report = [
            'ran_at' => $now->toIso8601String(),
            'venues_seen' => $venues->count(),
            'venues_backfilled' => $backfilled,
            'venues_skipped' => count($skipped),
            'empty_venues_defaulted_08_22' => $emptyVenueDefaults,
            'venue_hour_rows_created_per_weekday' => $weekdayRows,
            'unit_hour_rows_created' => $unitHourRows,
            'skipped' => $skipped,
        ];

        File::ensureDirectoryExists(storage_path('logs'));
        File::put(
            storage_path('logs/availability_backfill_report.txt'),
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );

        // Also echo for artisan migrate console output.
        echo PHP_EOL.'[availability backfill] venues_backfilled='.$backfilled
            .' skipped='.count($skipped)
            .' weekday_rows='.array_sum($weekdayRows)
            .' unit_hour_rows='.$unitHourRows.PHP_EOL;
        foreach ($weekdayRows as $d => $count) {
            echo "[availability backfill] weekday {$d}: {$count} rows".PHP_EOL;
        }
        foreach ($skipped as $row) {
            echo '[availability backfill] skipped venue #'.$row['venue_id'].' ('.$row['slug'].'): '.$row['reason'].PHP_EOL;
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('unit_hours')) {
            DB::table('unit_hours')->delete();
        }
        if (Schema::hasTable('venue_hours')) {
            DB::table('venue_hours')->delete();
        }
        if (Schema::hasColumn('workspaces', 'inherits_venue_hours')) {
            DB::table('workspaces')->update(['inherits_venue_hours' => true]);
        }
    }

    private function normalizeTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = (string) $value;
        if (preg_match('/^\d{2}:\d{2}$/', $raw)) {
            return $raw.':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $raw)) {
            return $raw;
        }

        return null;
    }
};
