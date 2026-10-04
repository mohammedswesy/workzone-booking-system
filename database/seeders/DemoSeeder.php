<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\WorkspaceStatus;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Realistic local/demo data. Never run in production (guarded by DatabaseSeeder).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Demo Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'phone' => '+970599000001',
        ]);

        $owner = User::factory()->owner()->create([
            'name' => 'Demo Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
            'phone' => '+970599000002',
        ]);

        $ownerTwo = User::factory()->owner()->create([
            'name' => 'Nour Spaces',
            'email' => 'nour@example.com',
            'password' => 'password',
        ]);

        $user = User::factory()->userRole()->create([
            'name' => 'Demo User',
            'email' => 'user@example.com',
            'password' => 'password',
            'phone' => '+970599000003',
        ]);

        User::factory()->userRole()->count(4)->create();

        $locations = collect([
            ['name' => 'Gaza Hub', 'city' => 'Gaza', 'address' => 'Omar Al-Mukhtar St'],
            ['name' => 'Ramallah Desk', 'city' => 'Ramallah', 'address' => 'Al-Irsal'],
            ['name' => 'Nablus Loft', 'city' => 'Nablus', 'address' => 'Rafidia'],
        ])->map(fn (array $row) => Location::query()->firstOrCreate(
            ['name' => $row['name']],
            ['city' => $row['city'], 'address' => $row['address']]
        ));

        $amenities = collect(['WiFi', 'Coffee', 'Parking', 'Printer', 'Meeting Room', 'AC'])
            ->map(fn (string $name) => Amenity::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            ));

        $workspaces = collect([
            [
                'name' => 'Coastal Focus Room',
                'location' => 'Gaza',
                'location_id' => $locations[0]->id,
                'capacity' => 12,
                'price_per_hour' => 18,
                'featured' => true,
                'owner_id' => $owner->id,
            ],
            [
                'name' => 'Quiet Desk Hall',
                'location' => 'Ramallah',
                'location_id' => $locations[1]->id,
                'capacity' => 24,
                'price_per_hour' => 22,
                'featured' => true,
                'owner_id' => $owner->id,
            ],
            [
                'name' => 'Creator Studio',
                'location' => 'Nablus',
                'location_id' => $locations[2]->id,
                'capacity' => 8,
                'price_per_hour' => 30,
                'featured' => false,
                'owner_id' => $ownerTwo->id,
            ],
        ])->map(function (array $row) {
            return Workspace::factory()->create([
                ...$row,
                'status' => WorkspaceStatus::Published,
                'opening_time' => '08:00:00',
                'closing_time' => '22:00:00',
                'description' => 'Bright coworking space with reliable Wi‑Fi and natural light.',
                'payment_instructions' => "Bank transfer to the owner.\nIBAN: PS92 PALS 0000 0000 4001 2345 6701\nMention your booking ID in the transfer note.",
                'payment_methods' => ['bank_transfer', 'wallet', 'cash'],
            ]);
        });

        foreach ($workspaces as $index => $workspace) {
            $workspace->amenities()->sync(
                $amenities->random(min(3, $amenities->count()))->pluck('id')->all()
            );
        }

        Offer::query()->create([
            'owner_id' => $owner->id,
            'workspace_id' => $workspaces[0]->id,
            'title' => 'Launch week 15% off',
            'discount_percent' => 15,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeeks(2),
            'is_active' => true,
        ]);

        $pending = Booking::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspaces[0]->id,
            'status' => BookingStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'start_at' => now()->addDays(2)->setTime(10, 0),
            'end_at' => now()->addDays(2)->setTime(13, 0),
            'hours' => 3,
            'total_price' => 54,
        ]);

        $awaiting = Booking::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspaces[1]->id,
            'status' => BookingStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'start_at' => now()->addDays(3)->setTime(14, 0),
            'end_at' => now()->addDays(3)->setTime(16, 0),
            'hours' => 2,
            'total_price' => 44,
        ]);

        Payment::factory()->create([
            'booking_id' => $awaiting->id,
            'provider' => PaymentProvider::Manual,
            'amount' => $awaiting->total_price,
            'status' => PaymentStatus::Pending,
            'metadata' => ['method' => 'bank_transfer'],
        ]);

        $paidBooking = Booking::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspaces[0]->id,
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'start_at' => now()->addDays(5)->setTime(9, 0),
            'end_at' => now()->addDays(5)->setTime(12, 0),
            'hours' => 3,
            'total_price' => 45.9,
        ]);

        Payment::factory()->paid()->create([
            'booking_id' => $paidBooking->id,
            'amount' => $paidBooking->total_price,
            'metadata' => ['method' => 'wallet'],
        ]);

        Booking::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspaces[2]->id,
            'status' => BookingStatus::Cancelled,
            'payment_status' => PaymentStatus::Unpaid,
            'start_at' => now()->subDays(2)->setTime(11, 0),
            'end_at' => now()->subDays(2)->setTime(13, 0),
            'hours' => 2,
            'total_price' => 60,
        ]);

        $this->command?->info('Demo seeded.');
        $this->command?->table(
            ['Role', 'Email', 'Password'],
            [
                ['admin', $admin->email, 'password'],
                ['owner', $owner->email, 'password'],
                ['owner', $ownerTwo->email, 'password'],
                ['user', $user->email, 'password'],
            ]
        );

        // Keep $pending referenced for future expansion / avoid unused lint noise.
        unset($pending);
    }
}
