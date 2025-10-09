<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Booking;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        // Owner
        $owner = User::factory()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'role' => 'owner',
            'password' => bcrypt('password'),
        ]);

        // User
        $user = User::factory()->create([
            'name' => 'User',
            'email' => 'user@example.com',
            'role' => 'user',
            'password' => bcrypt('password'),
        ]);

        // Workspaces للمالك
        $workspaces = Workspace::factory()->count(3)->create([
            'owner_id' => $owner->id,
        ]);

        // Bookings للمستخدم
        Booking::factory()->count(5)->create([
            'user_id' => $user->id,
            'workspace_id' => $workspaces->random()->id,
            'status' => 'pending', 
        ]);
    }
}
