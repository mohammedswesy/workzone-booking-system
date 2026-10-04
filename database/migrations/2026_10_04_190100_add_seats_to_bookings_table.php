<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FLAG BEFORE MYSQL: adds bookings.seats (int, min 1).
 * Existing bookings backfilled to 1 so stored totals (priced without a seats factor) stay valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('seats')->default(1)->after('hours');
        });

        DB::table('bookings')->update(['seats' => 1]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('seats');
        });
    }
};
