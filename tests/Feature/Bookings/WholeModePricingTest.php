<?php

use App\Actions\Bookings\CreateBooking;
use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Pricing\BookingPricingService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 09:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('prices whole mode as a flat hourly rate ignoring capacity seats', function () {
    $workspace = Workspace::factory()->create([
        'booking_mode' => BookingMode::Whole,
        'capacity' => 12,
        'price_per_hour' => '50.00',
    ]);

    $quote = app(BookingPricingService::class)->quote(
        $workspace,
        Carbon::parse('2026-10-10 10:00:00'),
        Carbon::parse('2026-10-10 12:00:00'),
        seats: 12,
    );

    expect($quote->baseAmount)->toBe('100.00')
        ->and($quote->finalAmount)->toBe('100.00')
        ->and($quote->seats)->toBe(12);
});

it('applies offers on whole-mode flat pricing', function () {
    $workspace = Workspace::factory()->create([
        'booking_mode' => BookingMode::Whole,
        'capacity' => 8,
        'price_per_hour' => '100.00',
    ]);
    Offer::create([
        'owner_id' => $workspace->owner_id,
        'workspace_id' => $workspace->id,
        'title' => 'Whole deal',
        'discount_percent' => 25,
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addWeek(),
    ]);

    $quote = app(BookingPricingService::class)->quote(
        $workspace,
        Carbon::parse('2026-10-10 10:00:00'),
        Carbon::parse('2026-10-10 12:00:00'),
        seats: 8,
    );

    expect($quote->baseAmount)->toBe('200.00')
        ->and($quote->finalAmount)->toBe('150.00');
});

it('keeps existing whole-workspace new booking price equal to the pre-seats formula', function () {
    $workspace = Workspace::factory()->create([
        'booking_mode' => BookingMode::Whole,
        'capacity' => 20,
        'price_per_hour' => '40.00',
        'opening_time' => '08:00:00',
        'closing_time' => '22:00:00',
    ]);
    $user = User::factory()->userRole()->create();

    $booking = app(CreateBooking::class)->handle(
        $user,
        $workspace,
        Carbon::parse('2026-10-10 10:00:00'),
        Carbon::parse('2026-10-10 13:00:00'),
    );

    expect($booking->seats)->toBe(20)
        ->and((string) $booking->total_price)->toBe('120.00'); // 40 × 3h, not × 20 seats
});

it('prices seat mode by seats', function () {
    $workspace = Workspace::factory()->create([
        'booking_mode' => BookingMode::Seat,
        'capacity' => 10,
        'price_per_hour' => '15.00',
    ]);

    $quote = app(BookingPricingService::class)->quote(
        $workspace,
        Carbon::parse('2026-10-10 10:00:00'),
        Carbon::parse('2026-10-10 12:00:00'),
        seats: 4,
    );

    expect($quote->baseAmount)->toBe('120.00')
        ->and($quote->finalAmount)->toBe('120.00');
});

it('blocks booking_mode changes when future pending or confirmed bookings exist', function () {
    $owner = User::factory()->owner()->create();
    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'booking_mode' => BookingMode::Whole,
        'capacity' => 6,
        'payment_instructions' => 'Pay cash at reception',
        'payment_methods' => ['cash'],
    ]);
    Booking::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Pending,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addHours(2),
    ]);

    $this->actingAs($owner)
        ->put(route('owner.workspaces.update', $workspace), [
            'name' => $workspace->name,
            'location' => $workspace->location,
            'capacity' => $workspace->capacity,
            'booking_mode' => BookingMode::Seat->value,
            'price_per_hour' => $workspace->price_per_hour,
            'status' => $workspace->status->value,
            'payment_instructions' => $workspace->payment_instructions,
            'payment_methods' => $workspace->payment_methods,
        ])
        ->assertSessionHasErrors('booking_mode');

    expect($workspace->fresh()->booking_mode)->toBe(BookingMode::Whole);
});

it('allows booking_mode changes when there are no active future bookings', function () {
    $owner = User::factory()->owner()->create();
    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'booking_mode' => BookingMode::Whole,
        'capacity' => 6,
        'payment_instructions' => 'Pay cash at reception',
        'payment_methods' => ['cash'],
    ]);
    Booking::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Cancelled,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addHours(2),
    ]);

    $this->actingAs($owner)
        ->put(route('owner.workspaces.update', $workspace), [
            'name' => $workspace->name,
            'location' => $workspace->location,
            'capacity' => $workspace->capacity,
            'booking_mode' => BookingMode::Seat->value,
            'price_per_hour' => $workspace->price_per_hour,
            'status' => $workspace->status->value,
            'payment_instructions' => $workspace->payment_instructions,
            'payment_methods' => $workspace->payment_methods,
        ])
        ->assertRedirect(route('owner.workspaces.index'));

    expect($workspace->fresh()->booking_mode)->toBe(BookingMode::Seat);
});

it('exposes distinct price labels per booking mode in locale files', function () {
    $en = json_decode(file_get_contents(resource_path('js/i18n/locales/en.json')), true);
    $ar = json_decode(file_get_contents(resource_path('js/i18n/locales/ar.json')), true);

    expect($en['owner']['pricePerHourSeat'])->toBe('Price per seat per hour')
        ->and($en['owner']['pricePerHourWhole'])->toBe('Price per hour for the whole space')
        ->and($en['spaces']['priceUnitSeat'])->toBe('Price per seat per hour')
        ->and($en['spaces']['priceUnitWhole'])->toBe('Price per hour for the whole space')
        ->and($ar['owner']['pricePerHourSeat'])->toBe('السعر لكل مقعد في الساعة')
        ->and($ar['owner']['pricePerHourWhole'])->toBe('السعر في الساعة للمساحة كاملة')
        ->and($ar['spaces']['priceUnitSeat'])->toBe('السعر لكل مقعد في الساعة')
        ->and($ar['spaces']['priceUnitWhole'])->toBe('السعر في الساعة للمساحة كاملة');
});
