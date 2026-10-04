<?php

use App\Actions\Bookings\CreateBooking;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Pricing\BookingPricingService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 09:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeOwnerWorkspace(array $workspaceAttrs = []): array
{
    $owner = User::factory()->owner()->create();
    $workspace = Workspace::factory()->create(array_merge([
        'owner_id' => $owner->id,
        'price_per_hour' => '100.00',
        'opening_time' => '08:00:00',
        'closing_time' => '22:00:00',
    ], $workspaceAttrs));

    return [$owner, $workspace];
}

it('creates a booking successfully with unpaid payment status', function () {
    [, $workspace] = makeOwnerWorkspace();
    $user = User::factory()->userRole()->create();
    $start = Carbon::parse('2026-10-10 10:00:00');
    $end = Carbon::parse('2026-10-10 12:00:00');

    $booking = app(CreateBooking::class)->handle($user, $workspace, $start, $end);

    expect($booking->status)->toBe(BookingStatus::Pending)
        ->and($booking->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and((string) $booking->total_price)->toBe('200.00')
        ->and($booking->hours)->toBe(2);
});

it('rejects overlapping bookings but allows back-to-back', function () {
    [, $workspace] = makeOwnerWorkspace();
    $user = User::factory()->userRole()->create();

    app(CreateBooking::class)->handle(
        $user,
        $workspace,
        Carbon::parse('2026-10-10 10:00:00'),
        Carbon::parse('2026-10-10 12:00:00'),
    );

    expect(fn () => app(CreateBooking::class)->handle(
        $user,
        $workspace,
        Carbon::parse('2026-10-10 11:00:00'),
        Carbon::parse('2026-10-10 13:00:00'),
    ))->toThrow(ValidationException::class);

    $backToBack = app(CreateBooking::class)->handle(
        $user,
        $workspace,
        Carbon::parse('2026-10-10 12:00:00'),
        Carbon::parse('2026-10-10 13:00:00'),
    );

    expect($backToBack)->toBeInstanceOf(Booking::class);
});

it('rejects bookings outside opening hours', function () {
    [, $workspace] = makeOwnerWorkspace([
        'opening_time' => '09:00:00',
        'closing_time' => '17:00:00',
    ]);
    $user = User::factory()->userRole()->create();

    expect(fn () => app(CreateBooking::class)->handle(
        $user,
        $workspace,
        Carbon::parse('2026-10-10 08:00:00'),
        Carbon::parse('2026-10-10 10:00:00'),
    ))->toThrow(ValidationException::class);
});

it('applies active offer discount with decimal-safe math', function () {
    [, $workspace] = makeOwnerWorkspace(['price_per_hour' => '100.00']);
    Offer::create([
        'owner_id' => $workspace->owner_id,
        'workspace_id' => $workspace->id,
        'title' => 'Spring',
        'discount_percent' => 25,
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $quote = app(BookingPricingService::class)->quote(
        $workspace->fresh(),
        Carbon::parse('2026-10-10 10:00:00'),
        Carbon::parse('2026-10-10 12:00:00'),
    );

    expect($quote->baseAmount)->toBe('200.00')
        ->and($quote->discountPercent)->toBe(25)
        ->and($quote->discountAmount)->toBe('50.00')
        ->and($quote->finalAmount)->toBe('150.00');
});

it('ignores expired offers when pricing', function () {
    [, $workspace] = makeOwnerWorkspace(['price_per_hour' => '80.00']);
    Offer::create([
        'owner_id' => $workspace->owner_id,
        'workspace_id' => $workspace->id,
        'title' => 'Old',
        'discount_percent' => 50,
        'is_active' => true,
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->subDay(),
    ]);

    $quote = app(BookingPricingService::class)->quote(
        $workspace->fresh(),
        Carbon::parse('2026-10-10 10:00:00'),
        Carbon::parse('2026-10-10 11:00:00'),
    );

    expect($quote->discountPercent)->toBe(0)
        ->and($quote->finalAmount)->toBe('80.00');
});

it('expires stale pending bookings via artisan command', function () {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Pending,
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ]);

    $this->artisan('bookings:expire-pending')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});

it('forbids owners from changing hours or price via update', function () {
    [$owner, $workspace] = makeOwnerWorkspace();
    $user = User::factory()->userRole()->create();
    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'hours' => 2,
        'total_price' => '200.00',
        'status' => BookingStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->put(route('owner.bookings.update', $booking), [
            'status' => BookingStatus::Confirmed->value,
            'hours' => 8,
            'total_price' => '1.00',
        ])
        ->assertRedirect();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->hours)->toBe(2)
        ->and((string) $booking->total_price)->toBe('200.00');
});

it('forbids regular users from creating bookings through policy boundary on store', function () {
    [, $workspace] = makeOwnerWorkspace();
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('user.bookings.store'), [
            'workspace_id' => $workspace->id,
            'start_at' => '2026-10-10 10:00:00',
            'end_at' => '2026-10-10 11:00:00',
        ])
        ->assertForbidden();
});
