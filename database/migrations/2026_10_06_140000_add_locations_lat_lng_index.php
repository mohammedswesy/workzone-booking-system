<?php

/**
 * FLAG BEFORE MYSQL (near-me):
 * Adds composite index on locations(lat, lng) for bounding-box near-me prefilter.
 * Reversible via down(). No data rewrite.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->index(['lat', 'lng'], 'locations_lat_lng_index');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex('locations_lat_lng_index');
        });
    }
};
