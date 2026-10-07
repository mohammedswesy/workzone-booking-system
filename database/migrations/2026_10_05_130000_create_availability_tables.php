<?php

/**
 * FLAG BEFORE MYSQL (phase 13 / Stage A):
 * - Creates venue_hours, unit_hours, availability_exceptions
 * - Adds venues.timezone, bookings_paused, bookings_paused_note
 * - Adds workspaces.inherits_venue_hours, bookings_paused, bookings_paused_note
 * - Does NOT drop workspaces.opening_time / closing_time (deprecated, kept for rollback)
 *
 * Idempotent: safe to resume after a partial MySQL failure (e.g. index name length).
 * Run on a data copy after migrate. Real rollback = restore backup; down() is best-effort.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('venues', 'timezone')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->string('timezone', 64)->default('Asia/Gaza')->after('featured');
            });
        }
        if (! Schema::hasColumn('venues', 'bookings_paused')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->boolean('bookings_paused')->default(false)->after('timezone');
            });
        }
        if (! Schema::hasColumn('venues', 'bookings_paused_note')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->string('bookings_paused_note')->nullable()->after('bookings_paused');
            });
        }

        if (! Schema::hasColumn('workspaces', 'inherits_venue_hours')) {
            Schema::table('workspaces', function (Blueprint $table) {
                $table->boolean('inherits_venue_hours')->default(true)->after('closing_time');
            });
        }
        if (! Schema::hasColumn('workspaces', 'bookings_paused')) {
            Schema::table('workspaces', function (Blueprint $table) {
                $table->boolean('bookings_paused')->default(false)->after('inherits_venue_hours');
            });
        }
        if (! Schema::hasColumn('workspaces', 'bookings_paused_note')) {
            Schema::table('workspaces', function (Blueprint $table) {
                $table->string('bookings_paused_note')->nullable()->after('bookings_paused');
            });
        }

        if (! Schema::hasTable('venue_hours')) {
            Schema::create('venue_hours', function (Blueprint $table) {
                $table->id();
                $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
                $table->unsignedTinyInteger('weekday'); // 0=Sunday … 6=Saturday (Carbon)
                $table->time('opens_at')->nullable();
                $table->time('closes_at')->nullable();
                $table->boolean('is_closed')->default(false);
                $table->timestamps();

                $table->unique(['venue_id', 'weekday']);
            });
        }

        if (! Schema::hasTable('unit_hours')) {
            Schema::create('unit_hours', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->unsignedTinyInteger('weekday');
                $table->time('opens_at')->nullable();
                $table->time('closes_at')->nullable();
                $table->boolean('is_closed')->default(false);
                $table->timestamps();

                $table->unique(['workspace_id', 'weekday']);
            });
        }

        if (! Schema::hasTable('availability_exceptions')) {
            Schema::create('availability_exceptions', function (Blueprint $table) {
                $table->id();
                $table->string('scope', 16); // venue|unit
                $table->foreignId('venue_id')->nullable()->constrained('venues')->cascadeOnDelete();
                $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->cascadeOnDelete();
                $table->date('starts_on');
                $table->date('ends_on');
                $table->string('type', 32); // closed|special_hours
                $table->string('reason')->nullable();
                $table->time('opens_at')->nullable();
                $table->time('closes_at')->nullable();
                $table->timestamps();

                // Short names: MySQL identifier limit is 64 chars.
                $table->index(['scope', 'venue_id', 'starts_on', 'ends_on'], 'avail_ex_venue_range_idx');
                $table->index(['scope', 'workspace_id', 'starts_on', 'ends_on'], 'avail_ex_unit_range_idx');
            });
        } else {
            $indexes = collect(DB::select('SHOW INDEX FROM availability_exceptions'))
                ->pluck('Key_name')
                ->unique()
                ->all();

            Schema::table('availability_exceptions', function (Blueprint $table) use ($indexes) {
                if (! in_array('avail_ex_venue_range_idx', $indexes, true)
                    && ! in_array('availability_exceptions_scope_venue_id_starts_on_ends_on_index', $indexes, true)) {
                    $table->index(['scope', 'venue_id', 'starts_on', 'ends_on'], 'avail_ex_venue_range_idx');
                }
                if (! in_array('avail_ex_unit_range_idx', $indexes, true)
                    && ! in_array('availability_exceptions_scope_workspace_id_starts_on_ends_on_index', $indexes, true)) {
                    $table->index(['scope', 'workspace_id', 'starts_on', 'ends_on'], 'avail_ex_unit_range_idx');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_exceptions');
        Schema::dropIfExists('unit_hours');
        Schema::dropIfExists('venue_hours');

        $workspaceCols = array_values(array_filter(
            ['inherits_venue_hours', 'bookings_paused', 'bookings_paused_note'],
            fn (string $col) => Schema::hasColumn('workspaces', $col)
        ));
        if ($workspaceCols !== []) {
            Schema::table('workspaces', function (Blueprint $table) use ($workspaceCols) {
                $table->dropColumn($workspaceCols);
            });
        }

        $venueCols = array_values(array_filter(
            ['timezone', 'bookings_paused', 'bookings_paused_note'],
            fn (string $col) => Schema::hasColumn('venues', $col)
        ));
        if ($venueCols !== []) {
            Schema::table('venues', function (Blueprint $table) use ($venueCols) {
                $table->dropColumn($venueCols);
            });
        }
    }
};
