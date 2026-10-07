<?php

use App\Enums\BookingStatus;
use App\Enums\VenueStatus;
use App\Enums\WorkspaceStatus;
use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\Location;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueHour;
use App\Models\Workspace;
use App\Services\Availability\AvailabilityService;
use Illuminate\Support\Carbon;

function seedOpenVenue(): array
{
    $owner = User::factory()->owner()->create(['is_active' => true]);
    $location = Location::factory()->create([
        'lat' => 31.5,
        'lng' => 34.47,
    ]);
    $venue = Venue::factory()->create([
        'owner_id' => $owner->id,
        'location_id' => $location->id,
        'status' => VenueStatus::Published,
        'timezone' => 'Asia/Gaza',
        'bookings_paused' => false,
    ]);
    for ($d = 0; $d <= 6; $d++) {
        VenueHour::query()->updateOrCreate(
            ['venue_id' => $venue->id, 'weekday' => $d],
            [
                'opens_at' => '09:00:00',
                'closes_at' => '17:00:00',
                'is_closed' => false,
            ]
        );
    }
    $unit = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'venue_id' => $venue->id,
        'status' => WorkspaceStatus::Published,
        'inherits_venue_hours' => true,
        'opening_time' => '09:00:00',
        'closing_time' => '17:00:00',
    ]);

    return compact('owner', 'venue', 'unit', 'location');
}

it('rejects bookings outside weekly hours with a localized reason', function () {
    ['unit' => $unit] = seedOpenVenue();
    $service = app(AvailabilityService::class);

    $start = Carbon::parse('2026-10-06 07:00:00', 'Asia/Gaza')->utc(); // Tuesday before open
    $end = Carbon::parse('2026-10-06 08:00:00', 'Asia/Gaza')->utc();

    $decision = $service->evaluate($unit, $start, $end);
    expect($decision->allowed)->toBeFalse()
        ->and($decision->reasonKey)->toBe('outside_hours');
});

it('honours closed weekdays', function () {
    ['venue' => $venue, 'unit' => $unit] = seedOpenVenue();
    VenueHour::query()->where('venue_id', $venue->id)->where('weekday', 5)->update(['is_closed' => true]); // Friday
    $service = app(AvailabilityService::class);

    $start = Carbon::parse('2026-10-09 10:00:00', 'Asia/Gaza')->utc(); // Friday
    $end = Carbon::parse('2026-10-09 11:00:00', 'Asia/Gaza')->utc();

    expect($service->evaluate($unit, $start, $end)->reasonKey)->toBe('closed_weekday');
});

it('applies closed exception ranges', function () {
    ['venue' => $venue, 'unit' => $unit] = seedOpenVenue();
    AvailabilityException::query()->create([
        'scope' => 'venue',
        'venue_id' => $venue->id,
        'starts_on' => '2026-10-12',
        'ends_on' => '2026-10-14',
        'type' => 'closed',
        'reason' => 'Maintenance',
    ]);
    $service = app(AvailabilityService::class);
    $start = Carbon::parse('2026-10-13 10:00:00', 'Asia/Gaza')->utc();
    $end = Carbon::parse('2026-10-13 11:00:00', 'Asia/Gaza')->utc();

    $decision = $service->evaluate($unit, $start, $end);
    expect($decision->allowed)->toBeFalse()
        ->and($decision->reasonKey)->toBe('closed_exception')
        ->and($decision->localizedMessage())->toContain('Maintenance');
});

it('applies special hours exceptions', function () {
    ['venue' => $venue, 'unit' => $unit] = seedOpenVenue();
    AvailabilityException::query()->create([
        'scope' => 'venue',
        'venue_id' => $venue->id,
        'starts_on' => '2026-10-07',
        'ends_on' => '2026-10-07',
        'type' => 'special_hours',
        'reason' => 'Short day',
        'opens_at' => '10:00:00',
        'closes_at' => '14:00:00',
    ]);
    $service = app(AvailabilityService::class);

    $badStart = Carbon::parse('2026-10-07 09:00:00', 'Asia/Gaza')->utc();
    $badEnd = Carbon::parse('2026-10-07 10:30:00', 'Asia/Gaza')->utc();
    expect($service->evaluate($unit, $badStart, $badEnd)->allowed)->toBeFalse();

    $okStart = Carbon::parse('2026-10-07 11:00:00', 'Asia/Gaza')->utc();
    $okEnd = Carbon::parse('2026-10-07 12:00:00', 'Asia/Gaza')->utc();
    expect($service->evaluate($unit, $okStart, $okEnd)->allowed)->toBeTrue();
});

it('blocks bookings when paused', function () {
    ['venue' => $venue, 'unit' => $unit] = seedOpenVenue();
    $venue->update(['bookings_paused' => true, 'bookings_paused_note' => 'Renovations']);
    $service = app(AvailabilityService::class);
    $start = Carbon::parse('2026-10-06 10:00:00', 'Asia/Gaza')->utc();
    $end = Carbon::parse('2026-10-06 11:00:00', 'Asia/Gaza')->utc();

    expect($service->evaluate($unit->fresh(['venue']), $start, $end)->reasonKey)->toBe('paused');
});

it('lets unit hours override venue hours', function () {
    ['venue' => $venue, 'unit' => $unit] = seedOpenVenue();
    $unit->update(['inherits_venue_hours' => false]);
    for ($d = 0; $d <= 6; $d++) {
        $unit->hours()->create([
            'weekday' => $d,
            'opens_at' => '12:00:00',
            'closes_at' => '20:00:00',
            'is_closed' => false,
        ]);
    }
    $service = app(AvailabilityService::class);
    $early = Carbon::parse('2026-10-06 10:00:00', 'Asia/Gaza')->utc();
    $earlyEnd = Carbon::parse('2026-10-06 11:00:00', 'Asia/Gaza')->utc();
    expect($service->evaluate($unit->fresh(['hours', 'venue.hours']), $early, $earlyEnd)->allowed)->toBeFalse();

    $ok = Carbon::parse('2026-10-06 13:00:00', 'Asia/Gaza')->utc();
    $okEnd = Carbon::parse('2026-10-06 14:00:00', 'Asia/Gaza')->utc();
    expect($service->evaluate($unit->fresh(['hours', 'venue.hours']), $ok, $okEnd)->allowed)->toBeTrue();
});

it('rejects midnight crossing', function () {
    ['unit' => $unit] = seedOpenVenue();
    VenueHour::query()->update(['opens_at' => '08:00:00', 'closes_at' => '23:00:00']);
    $service = app(AvailabilityService::class);
    $start = Carbon::parse('2026-10-06 22:00:00', 'Asia/Gaza')->utc();
    $end = Carbon::parse('2026-10-07 01:00:00', 'Asia/Gaza')->utc();

    expect($service->evaluate($unit, $start, $end)->reasonKey)->toBe('no_midnight_crossing');
});

it('lists conflicting future bookings for a closure', function () {
    ['owner' => $owner, 'venue' => $venue, 'unit' => $unit] = seedOpenVenue();
    $user = User::factory()->userRole()->create();
    Booking::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $unit->id,
        'status' => BookingStatus::Confirmed,
        'start_at' => now()->addDays(3)->setTime(10, 0),
        'end_at' => now()->addDays(3)->setTime(12, 0),
    ]);

    $conflicts = app(AvailabilityService::class)->conflictingFutureBookings($venue);
    expect($conflicts)->toHaveCount(1)
        ->and($conflicts->first()->user->name)->toBe($user->name);
});

it('forbids another owner from editing availability', function () {
    ['venue' => $venue] = seedOpenVenue();
    $other = User::factory()->owner()->create();

    $this->actingAs($other)
        ->get(route('owner.venues.availability.edit', $venue))
        ->assertForbidden();
});

it('exposes only public fields on the map JSON endpoint', function () {
    ['venue' => $venue, 'location' => $location] = seedOpenVenue();
    $location->update(['lat' => 31.52, 'lng' => 34.45]);

    $this->getJson(route('spaces.map'))
        ->assertOk()
        ->assertJsonStructure(['markers' => [['id', 'name', 'slug', 'lat', 'lng', 'from_price', 'url']]])
        ->assertJsonMissing(['owner_id'])
        ->assertJsonMissing(['payment_instructions']);
});

it('validates coordinate ranges on venue update', function () {
    ['owner' => $owner, 'venue' => $venue] = seedOpenVenue();

    $this->actingAs($owner)
        ->put(route('owner.venues.update', $venue), [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'status' => VenueStatus::Published->value,
            'lat' => 120,
            'lng' => 34,
        ])
        ->assertSessionHasErrors('lat');
});

it('evaluates availability in the venue timezone across a DST-style offset change', function () {
    ['venue' => $venue, 'unit' => $unit] = seedOpenVenue();
    // Europe/Berlin observes DST; Asia/Gaza does not — prove wall-clock hours are venue-local.
    $venue->update(['timezone' => 'Europe/Berlin']);
    $service = app(AvailabilityService::class);

    // 2026-03-29 is after EU spring-forward; 10:00 local must still be admitted.
    $start = Carbon::parse('2026-03-30 10:00:00', 'Europe/Berlin')->utc();
    $end = Carbon::parse('2026-03-30 11:00:00', 'Europe/Berlin')->utc();
    expect($service->evaluate($unit->fresh(['venue', 'venue.hours']), $start, $end)->allowed)->toBeTrue();

    $early = Carbon::parse('2026-03-30 07:00:00', 'Europe/Berlin')->utc();
    $earlyEnd = Carbon::parse('2026-03-30 08:00:00', 'Europe/Berlin')->utc();
    expect($service->evaluate($unit->fresh(['venue', 'venue.hours']), $early, $earlyEnd)->allowed)->toBeFalse();
});

it('keeps pre-backfill opening_time behavior after copying into venue_hours', function () {
    ['unit' => $unit, 'venue' => $venue] = seedOpenVenue();
    // Simulate legacy columns differing from a naive default.
    $unit->update(['opening_time' => '10:00:00', 'closing_time' => '16:00:00', 'inherits_venue_hours' => true]);
    VenueHour::query()->where('venue_id', $venue->id)->update([
        'opens_at' => '10:00:00',
        'closes_at' => '16:00:00',
    ]);
    $service = app(AvailabilityService::class);

    $ok = Carbon::parse('2026-10-06 11:00:00', 'Asia/Gaza')->utc();
    $okEnd = Carbon::parse('2026-10-06 12:00:00', 'Asia/Gaza')->utc();
    expect($service->evaluate($unit->fresh(['venue.hours']), $ok, $okEnd)->allowed)->toBeTrue();

    $late = Carbon::parse('2026-10-06 16:30:00', 'Asia/Gaza')->utc();
    $lateEnd = Carbon::parse('2026-10-06 17:00:00', 'Asia/Gaza')->utc();
    expect($service->evaluate($unit->fresh(['venue.hours']), $late, $lateEnd)->allowed)->toBeFalse();
});

it('agrees between price preview availability and booking evaluate', function () {
    ['unit' => $unit] = seedOpenVenue();
    $user = User::factory()->userRole()->create();
    $service = app(AvailabilityService::class);

    $start = Carbon::parse('2026-10-06 07:00:00', 'Asia/Gaza')->utc();
    $end = Carbon::parse('2026-10-06 08:00:00', 'Asia/Gaza')->utc();

    $this->actingAs($user)
        ->getJson(route('user.bookings.availability', [
            'workspace_id' => $unit->id,
            'start_at' => '2026-10-06 07:00',
            'end_at' => '2026-10-06 08:00',
        ]))
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('reason_key', $service->evaluate($unit, $start, $end)->reasonKey);
});

it('omits markers for venues without coordinates', function () {
    ['venue' => $venue, 'location' => $location] = seedOpenVenue();
    $location->update(['lat' => null, 'lng' => null]);

    $payload = $this->getJson(route('spaces.map'))->assertOk()->json('markers');
    expect(collect($payload)->firstWhere('id', $venue->id))->toBeNull();
});

it('hides draft venues from the map JSON endpoint', function () {
    ['venue' => $venue, 'location' => $location] = seedOpenVenue();
    $location->update(['lat' => 31.5, 'lng' => 34.4]);
    $venue->update(['status' => VenueStatus::Draft]);

    $payload = $this->getJson(route('spaces.map'))->assertOk()->json('markers');
    expect(collect($payload)->firstWhere('id', $venue->id))->toBeNull();
});

it('reports venues without complete hours via availability:verify and flags the fallback', function () {
    ['owner' => $owner, 'venue' => $venue, 'unit' => $unit] = seedOpenVenue();
    \App\Models\VenueHour::query()->where('venue_id', $venue->id)->delete();

    $service = app(AvailabilityService::class);
    expect($service->venueNeedsHoursConfig($venue->fresh()))->toBeTrue();

    $start = Carbon::parse('2026-10-06 10:00:00', 'Asia/Gaza')->utc();
    $end = Carbon::parse('2026-10-06 11:00:00', 'Asia/Gaza')->utc();
    // Fallback still allows the booking window (temporary safety net).
    expect($service->evaluate($unit->fresh(['venue']), $start, $end)->allowed)->toBeTrue();

    $this->artisan('availability:verify')->assertFailed();

    $cached = \Illuminate\Support\Facades\Cache::get('availability.verify.last');
    expect($cached['ok'])->toBeFalse()
        ->and($cached['issue_count'])->toBeGreaterThan(0)
        ->and(collect($cached['issues'])->contains(fn ($i) => ($i['code'] ?? '') === 'venue_missing_hours'))->toBeTrue();

    $this->actingAs($owner)
        ->get(route('owner.venues.show', $venue))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hours_config_needed', true));
});

it('seeds seven weekday hours when a venue is created', function () {
    $owner = User::factory()->owner()->create();
    $venue = Venue::factory()->create(['owner_id' => $owner->id]);

    expect(\App\Models\VenueHour::query()->where('venue_id', $venue->id)->count())->toBe(7)
        ->and(app(\App\Services\Availability\VenueHoursEnsuring::class)->isComplete($venue))->toBeTrue()
        ->and(app(AvailabilityService::class)->venueNeedsHoursConfig($venue))->toBeFalse();
});

it('requires acknowledgment and lists conflicts when pausing over future bookings', function () {
    ['owner' => $owner, 'venue' => $venue, 'unit' => $unit] = seedOpenVenue();
    $user = User::factory()->userRole()->create();
    Booking::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $unit->id,
        'status' => BookingStatus::Confirmed,
        'start_at' => now()->addDays(2)->setTime(10, 0),
        'end_at' => now()->addDays(2)->setTime(12, 0),
    ]);

    $this->actingAs($owner)
        ->from(route('owner.venues.availability.edit', $venue))
        ->put(route('owner.venues.availability.pause', $venue), [
            'bookings_paused' => true,
            'bookings_paused_note' => 'Maintenance',
            'acknowledge_conflicts' => false,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('acknowledge_conflicts')
        ->assertSessionHas('availability_conflicts');

    $conflicts = session('availability_conflicts');
    expect($conflicts)->toHaveCount(1)
        ->and($conflicts[0]['user'])->toBe($user->name)
        ->and($conflicts[0]['unit'])->toBe($unit->name);

    // Existing booking remains confirmed — never auto-cancelled.
    expect(Booking::query()->where('workspace_id', $unit->id)->where('status', BookingStatus::Confirmed)->count())->toBe(1);
});
