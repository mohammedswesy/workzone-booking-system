<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FLAG (MySQL): enforce workspaces.venue_id and type NOT NULL after backfill.
 * Fails if any row still null. Real rollback = restore backup.
 */
return new class extends Migration
{
    public function up(): void
    {
        $missingVenue = DB::table('workspaces')->whereNull('venue_id')->count();
        $missingType = DB::table('workspaces')->whereNull('type')->count();
        if ($missingVenue > 0 || $missingType > 0) {
            throw new \RuntimeException(
                "Cannot enforce NOT NULL: {$missingVenue} unit(s) missing venue_id, {$missingType} missing type. Run backfill first."
            );
        }

        Schema::table('workspaces', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable(false)->change();
            $table->string('type', 32)->nullable(false)->default('other')->change();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->change();
            $table->string('type', 32)->nullable()->change();
        });
    }
};
