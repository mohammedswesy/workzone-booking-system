<?php

/**
 * FLAG BEFORE MYSQL (demo images):
 * Adds venue_images.is_demo and venue_images.caption.
 * Reversible via down(). No data rewrite.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_images', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('sort_order');
            $table->string('caption')->nullable()->after('is_demo');
            $table->index('is_demo');
        });
    }

    public function down(): void
    {
        Schema::table('venue_images', function (Blueprint $table) {
            $table->dropIndex(['is_demo']);
            $table->dropColumn(['is_demo', 'caption']);
        });
    }
};
