<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FLAG (MySQL): creates venues, venue_images, amenity_venue, venue_slug_redirects.
 * Reversible. Does not drop any existing columns.
 * Real rollback on production = restore DB backup; down() is best-effort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('status', 32)->default('draft');
            $table->boolean('featured')->default(false);
            $table->timestamps();

            $table->index(['status', 'featured']);
            $table->index('owner_id');
        });

        Schema::create('venue_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('path');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['venue_id', 'is_primary']);
            $table->index(['venue_id', 'sort_order']);
            $table->index('path');
        });

        Schema::create('amenity_venue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('amenity_id')->constrained('amenities')->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['amenity_id', 'venue_id']);
        });

        Schema::create('venue_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_slug_redirects');
        Schema::dropIfExists('amenity_venue');
        Schema::dropIfExists('venue_images');
        Schema::dropIfExists('venues');
    }
};
