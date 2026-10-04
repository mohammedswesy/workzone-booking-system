<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        Schema::create('amenity_workspace', function (Blueprint $table) {
            $table->id();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->unique(['amenity_id', 'workspace_id']);
        });

        Schema::create('workspace_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['workspace_id', 'is_primary']);
            $table->index(['workspace_id', 'sort_order']);
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->string('status', 32)->default('published')->after('image_url');
            $table->boolean('featured')->default(false)->after('status');
            $table->foreignId('location_id')->nullable()->after('location')->constrained('locations')->nullOnDelete();
        });

        $this->backfillWorkspaces();

        Schema::table('workspaces', function (Blueprint $table) {
            $table->unique('slug');
            $table->index(['status', 'featured']);
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropIndex(['status', 'featured']);
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn(['slug', 'status', 'featured']);
        });

        Schema::dropIfExists('workspace_images');
        Schema::dropIfExists('amenity_workspace');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('locations');
    }

    private function backfillWorkspaces(): void
    {
        $locationCache = [];

        foreach (DB::table('workspaces')->orderBy('id')->cursor() as $workspace) {
            $slugBase = Str::slug($workspace->name) ?: 'workspace-'.$workspace->id;
            $slug = $slugBase;
            $i = 1;
            while (DB::table('workspaces')->where('slug', $slug)->where('id', '!=', $workspace->id)->exists()) {
                $slug = $slugBase.'-'.$i++;
            }

            $locationId = null;
            $locationName = trim((string) $workspace->location);
            if ($locationName !== '') {
                if (! isset($locationCache[$locationName])) {
                    $locationCache[$locationName] = DB::table('locations')->insertGetId([
                        'name' => $locationName,
                        'address' => $locationName,
                        'city' => $locationName,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $locationId = $locationCache[$locationName];
            }

            DB::table('workspaces')->where('id', $workspace->id)->update([
                'slug' => $slug,
                'status' => 'published',
                'featured' => false,
                'location_id' => $locationId,
            ]);

            if (! empty($workspace->image_url)) {
                DB::table('workspace_images')->insert([
                    'workspace_id' => $workspace->id,
                    'path' => $workspace->image_url,
                    'is_primary' => true,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
