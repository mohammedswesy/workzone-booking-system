<?php

/**
 * FLAG BEFORE MYSQL: creates platform_settings key/value store (e.g. commission_percent).
 * Reversible via down().
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('platform_settings')->insert([
            [
                'key' => 'commission_percent',
                'value' => (string) config('payments.commission_percent', 0),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
