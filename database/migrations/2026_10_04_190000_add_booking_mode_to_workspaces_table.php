<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FLAG BEFORE MYSQL: adds workspaces.booking_mode.
 * Existing rows backfilled to `whole` (previous exclusive-slot behavior).
 * New workspaces default to `seat`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('booking_mode', 16)->default('seat')->after('capacity');
        });

        DB::table('workspaces')->update(['booking_mode' => 'whole']);
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('booking_mode');
        });
    }
};
