<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $owner = User::factory()->owner()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
        ]);

        $user = User::factory()->userRole()->create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $workspaces = Workspace::factory()->count(3)->create([
            'owner_id' => $owner->id,
        ]);

        Booking::factory()->count(5)->create([
            'user_id' => $user->id,
            'workspace_id' => $workspaces->random()->id,
            'status' => BookingStatus::Pending,
        ]);
    }
}
