<?php

namespace Database\Seeders;

use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\VenueStatus;
use App\Enums\WorkspaceStatus;
use App\Enums\WorkspaceType;
use App\Models\Amenity;
use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueHour;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Realistic local/demo data. Never run in production (guarded by DatabaseSeeder).
 * Seeds multi-unit venues so public list, unit picker, and owner checklist are exercised.
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
            ['name' => 'Gaza Hub', 'city' => 'Gaza', 'address' => 'Omar Al-Mukhtar St', 'lat' => 31.5017, 'lng' => 34.4668],
            ['name' => 'Khan Younis Desk', 'city' => 'Khan Younis', 'address' => 'Jamal Abdel Nasser St', 'lat' => 31.3462, 'lng' => 34.3063],
            ['name' => 'Dubai Marina Desk', 'city' => 'Dubai', 'address' => 'Marina Walk', 'lat' => 25.0805, 'lng' => 55.1403],
            ['name' => 'Riyadh Olaya Hub', 'city' => 'Riyadh', 'address' => 'Olaya St', 'lat' => 24.7136, 'lng' => 46.6753],
            ['name' => 'Kuwait Sharq Loft', 'city' => 'Kuwait City', 'address' => 'Gulf Road', 'lat' => 29.3759, 'lng' => 47.9774],
        ])->map(fn (array $row) => Location::query()->updateOrCreate(
            ['name' => $row['name']],
            ['city' => $row['city'], 'address' => $row['address'], 'lat' => $row['lat'], 'lng' => $row['lng']]
        ));

        $amenities = collect(['WiFi', 'Coffee', 'Parking', 'Printer', 'Meeting Room', 'AC'])
            ->map(fn (string $name) => Amenity::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            ));

        $coastal = Venue::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Coastal Cowork',
            'slug' => 'coastal-cowork',
            'description' => 'Bright seaside building with hot desks and meeting rooms.',
            'location_id' => $locations[0]->id,
            'address' => 'Omar Al-Mukhtar St',
            'status' => VenueStatus::Published,
            'featured' => true,
        ]);
        $coastal->amenities()->sync($amenities->take(3)->pluck('id'));

        $khan = Venue::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Khan Younis Hub',
            'slug' => 'khan-younis-hub',
            'description' => 'Neighborhood desks in Khan Younis.',
            'location_id' => $locations[1]->id,
            'address' => 'Jamal Abdel Nasser St',
            'status' => VenueStatus::Published,
            'featured' => false,
        ]);
        $khan->amenities()->sync($amenities->take(2)->pluck('id'));

        $quiet = Venue::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Quiet Desk Hall',
            'slug' => 'quiet-desk-hall',
            'description' => 'Focused desks overlooking Dubai Marina.',
            'location_id' => $locations[2]->id,
            'address' => 'Marina Walk',
            'status' => VenueStatus::Published,
            'featured' => true,
        ]);
        $quiet->amenities()->sync($amenities->slice(1, 3)->pluck('id'));

        $studio = Venue::query()->create([
            'owner_id' => $ownerTwo->id,
            'name' => 'Creator Studio',
            'slug' => 'creator-studio',
            'description' => 'Creative loft in Riyadh Olaya.',
            'location_id' => $locations[3]->id,
            'address' => 'Olaya St',
            'status' => VenueStatus::Published,
            'featured' => false,
        ]);
        $studio->amenities()->sync($amenities->slice(2, 3)->pluck('id'));

        $kuwait = Venue::query()->create([
            'owner_id' => $ownerTwo->id,
            'name' => 'Sharq Waterfront',
            'slug' => 'sharq-waterfront',
            'description' => 'Gulf Road coworking in Kuwait City.',
            'location_id' => $locations[4]->id,
            'address' => 'Gulf Road',
            'status' => VenueStatus::Published,
            'featured' => false,
        ]);
        $kuwait->amenities()->sync($amenities->slice(0, 4)->pluck('id'));

        foreach ([$coastal, $khan, $quiet, $studio, $kuwait] as $venue) {
            for ($d = 0; $d <= 6; $d++) {
                VenueHour::query()->updateOrCreate(
                    ['venue_id' => $venue->id, 'weekday' => $d],
                    [
                        'opens_at' => '08:00:00',
                        'closes_at' => '22:00:00',
                        'is_closed' => $d === 5, // Friday closed for demo variety
                    ]
                );
            }
        }

        AvailabilityException::query()->create([
            'scope' => 'venue',
            'venue_id' => $coastal->id,
            'starts_on' => now()->addWeeks(2)->toDateString(),
            'ends_on' => now()->addWeeks(2)->addDays(1)->toDateString(),
            'type' => 'closed',
            'reason' => 'Building maintenance',
        ]);

        $paymentCopy = "Bank transfer to the owner.\nIBAN: PS92 PALS 0000 0000 4001 2345 6701\nMention your booking ID in the transfer note.";

        $units = collect([
            $this->makeUnit($coastal, [
                'name' => 'Hot Desk Zone',
                'type' => WorkspaceType::HotDesk,
                'booking_mode' => BookingMode::Seat,
                'capacity' => 20,
                'price_per_hour' => 12,
            ], $paymentCopy),
            $this->makeUnit($coastal, [
                'name' => 'Meeting Room A',
                'type' => WorkspaceType::MeetingRoom,
                'booking_mode' => BookingMode::Whole,
                'capacity' => 8,
                'price_per_hour' => 28,
            ], $paymentCopy),
            $this->makeUnit($coastal, [
                'name' => 'Private Office',
                'type' => WorkspaceType::PrivateOffice,
                'booking_mode' => BookingMode::Whole,
                'capacity' => 4,
                'price_per_hour' => 35,
            ], $paymentCopy),
            $this->makeUnit($quiet, [
                'name' => 'Main Hall',
                'type' => WorkspaceType::HotDesk,
                'booking_mode' => BookingMode::Seat,
                'capacity' => 24,
                'price_per_hour' => 22,
            ], $paymentCopy),
            $this->makeUnit($studio, [
                'name' => 'Studio Floor',
                'type' => WorkspaceType::Other,
                'booking_mode' => BookingMode::Whole,
                'capacity' => 8,
                'price_per_hour' => 30,
            ], $paymentCopy),
            $this->makeUnit($khan, [
                'name' => 'Open Desk',
                'type' => WorkspaceType::HotDesk,
                'booking_mode' => BookingMode::Seat,
                'capacity' => 16,
                'price_per_hour' => 10,
            ], $paymentCopy),
            $this->makeUnit($kuwait, [
                'name' => 'Bay View Desk',
                'type' => WorkspaceType::HotDesk,
                'booking_mode' => BookingMode::Seat,
                'capacity' => 18,
                'price_per_hour' => 26,
            ], $paymentCopy),
        ]);

        foreach ($units as $unit) {
            $unit->amenities()->sync(
                $amenities->random(min(3, $amenities->count()))->pluck('id')->all()
            );
        }

        Offer::query()->create([
            'owner_id' => $owner->id,
            'workspace_id' => $units[0]->id,
            'venue_id' => null,
            'title' => 'Hot desk launch 15% off',
            'discount_percent' => 15,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeeks(2),
            'is_active' => true,
        ]);

        Offer::query()->create([
            'owner_id' => $owner->id,
            'workspace_id' => null,
            'venue_id' => $quiet->id,
            'title' => 'Venue-wide 10% this month',
            'discount_percent' => 10,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        $pending = Booking::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $units[0]->id,
            'status' => BookingStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'start_at' => now()->addDays(2)->setTime(10, 0),
            'end_at' => now()->addDays(2)->setTime(13, 0),
            'hours' => 3,
            'total_price' => 30.6,
        ]);

        $awaiting = Booking::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $units[3]->id,
            'status' => BookingStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'start_at' => now()->addDays(3)->setTime(14, 0),
            'end_at' => now()->addDays(3)->setTime(16, 0),
            'hours' => 2,
            'total_price' => 39.6,
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
            'workspace_id' => $units[1]->id,
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'start_at' => now()->addDays(5)->setTime(9, 0),
            'end_at' => now()->addDays(5)->setTime(12, 0),
            'hours' => 3,
            'total_price' => 84,
        ]);

        Payment::factory()->paid()->create([
            'booking_id' => $paidBooking->id,
            'amount' => $paidBooking->total_price,
            'metadata' => ['method' => 'wallet'],
        ]);

        Booking::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $units[4]->id,
            'status' => BookingStatus::Cancelled,
            'payment_status' => PaymentStatus::Unpaid,
            'start_at' => now()->subDays(2)->setTime(11, 0),
            'end_at' => now()->subDays(2)->setTime(13, 0),
            'hours' => 2,
            'total_price' => 60,
        ]);

        $demoImages = app(\App\Services\Venues\DemoVenueImageService::class);
        foreach (Venue::query()->orderBy('id')->get() as $venue) {
            $demoImages->attachForVenue($venue);
        }

        $this->command?->info('Demo seeded (multi-unit venues + demo gallery images).');
        $this->command?->table(
            ['Role', 'Email', 'Password'],
            [
                ['admin', $admin->email, 'password'],
                ['owner', $owner->email, 'password'],
                ['owner', $ownerTwo->email, 'password'],
                ['user', $user->email, 'password'],
            ]
        );

        unset($pending);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makeUnit(Venue $venue, array $attrs, string $paymentCopy): Workspace
    {
        return Workspace::query()->create([
            'venue_id' => $venue->id,
            'owner_id' => $venue->owner_id,
            'name' => $attrs['name'],
            'location' => $venue->address ?: $venue->name,
            'location_id' => $venue->location_id,
            'description' => null,
            'capacity' => $attrs['capacity'],
            'booking_mode' => $attrs['booking_mode'],
            'type' => $attrs['type'],
            'price_per_hour' => $attrs['price_per_hour'],
            'opening_time' => '08:00:00',
            'closing_time' => '22:00:00',
            'status' => WorkspaceStatus::Published,
            'featured' => false,
            'payment_instructions' => $paymentCopy,
            'payment_methods' => ['bank_transfer', 'wallet', 'cash'],
        ]);
    }
}
