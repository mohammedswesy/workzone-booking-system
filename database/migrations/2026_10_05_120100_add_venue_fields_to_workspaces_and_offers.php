<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FLAG (MySQL): additive columns only — workspaces.venue_id/type (nullable),
 * offers.venue_id, offers.workspace_id nullable.
 * Does NOT drop opening_time/closing_time or any existing column.
 * Real rollback = restore backup; down() is best-effort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->after('owner_id')->constrained('venues')->restrictOnDelete();
            $table->string('type', 32)->nullable()->after('booking_mode');
            $table->index('venue_id');
            $table->index('type');
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->after('workspace_id')->constrained('venues')->cascadeOnDelete();
            $table->index('venue_id');
        });

        // Allow venue-scoped offers (workspace_id XOR venue_id enforced in app + backfill).
        Schema::table('offers', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venue_id');
        });

        // Best-effort: restore NOT NULL only if no null workspace_id rows remain.
        Schema::table('offers', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable(false)->change();
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venue_id');
            $table->dropColumn('type');
        });
    }
};
