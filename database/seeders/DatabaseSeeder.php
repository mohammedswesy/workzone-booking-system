<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Production seed: skipping demo accounts and sample data (no known passwords).');

            return;
        }

        $this->call(DemoSeeder::class);
    }
}
