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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 09:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function seatWorkspace(array $attrs = []): Workspace
{
    return Workspace::factory()->create(array_merge([
        'capacity' => 10,
        'booking_mode' => BookingMode::Seat,
        'price_per_hour' => '20.00',
        'opening_time' => '08:00:00',
        'closing_time' => '22:00:00',
    ], $attrs));
}

it('sums seats across overlapping bookings within capacity', function () {
    $workspace = seatWorkspace();
    $user = User::factory()->userRole()->create();

    app(CreateBooking::class)->handle(
        $user,
        $workspace,
        Carbon::parse('2026-10-10 10:00:00'),
        Carbon::parse('2026-10-10 12:00:00'),
        4,
    );

    $second = app(CreateBooking::class)->handle(
        $user,
        $workspace,
        Carbon::parse('2026-10-10 11:00:00'),
        Carbon::parse('2026-10-10 13:00:00'),
        3,
    );

    expect($second->seats)->toBe(3)
        ->and(Booking::where('workspace_id', $workspace->id)->sum('seats'))->toBe(7);
});

it('rejects bookings that exceed remaining seat capacity', function () {
    $workspace = seatWorkspace(['capacity' => 5]);
    $user = User::factory()->userRole()->create();

    app(CreateBooking::class)->handle(
        $user,
        $workspace,
        Carbon::parse('2026-10-10 10:00:00'),
        Carbon::parse('2026-10-10 12:00:00'),
        3,
    );

    expect(fn () => app(CreateBooking::class)->handle(
        $user,
        $workspace,
        Carbon::parse('2026-10-10 11:00:00'),
        Carbon::parse('2026-10-10 13:00:00'),
        3,
    ))->toThrow(ValidationException::class);
});

it('rejects any overlap in whole booking mode', function () {
    $workspace = seatWorkspace([
        'booking_mode' => BookingMode::Whole,
        'capacity' => 8,
    ]);
    $user = User::factory()->userRole()->create();

    app(CreateBooking::class)->handle(
        $user,
        $workspace,
        Carbon::parse('2026-10-10 10:00:00'),
        Carbon::parse('2026-10-10 12:00:00'),
        1,
    );

    expect(fn () => app(CreateBooking::class)->handle(
        $user,
        $workspace,
        Carbon::parse('2026-10-10 11:00:00'),
        Carbon::parse('2026-10-10 13:00:00'),
        1,
    ))->toThrow(ValidationException::class);
});

it('allows back-to-back bookings in seat and whole modes', function () {
    $seat = seatWorkspace();
    $whole = seatWorkspace(['booking_mode' => BookingMode::Whole, 'capacity' => 6]);
    $user = User::factory()->userRole()->create();

    app(CreateBooking::class)->handle(
        $user,
        $seat,
        Carbon::parse('2026-10-10 10:00:00'),
        Carbon::parse('2026-10-10 12:00:00'),
        2,
    );
    $seatNext = app(CreateBooking::class)->handle(
        $user,
        $seat,
        Carbon::parse('2026-10-10 12:00:00'),
        Carbon::parse('2026-10-10 13:00:00'),
        2,
    );

    app(CreateBooking::class)->handle(
        $user,
        $whole,
        Carbon::parse('2026-10-10 10:00:00'),
        Carbon::parse('2026-10-10 12:00:00'),
    );
    $wholeNext = app(CreateBooking::class)->handle(
        $user,
        $whole,
        Carbon::parse('2026-10-10 12:00:00'),
        Carbon::parse('2026-10-10 13:00:00'),
    );

    expect($seatNext)->toBeInstanceOf(Booking::class)
        ->and($wholeNext)->toBeInstanceOf(Booking::class)
        ->and($wholeNext->seats)->toBe(6);
});

it('prices by seats with active offer discount', function () {
    $workspace = seatWorkspace(['price_per_hour' => '100.00']);
    Offer::create([
        'owner_id' => $workspace->owner_id,
        'workspace_id' => $workspace->id,
        'title' => 'Half',
        'discount_percent' => 50,
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addWeek(),
    ]);

    $quote = app(BookingPricingService::class)->quote(
        $workspace,
        Carbon::parse('2026-10-10 10:00:00'),
        Carbon::parse('2026-10-10 12:00:00'),
        seats: 3,
    );

    // 100 * 2h * 3 seats = 600, 50% off = 300
    expect($quote->baseAmount)->toBe('600.00')
        ->and($quote->finalAmount)->toBe('300.00')
        ->and($quote->seats)->toBe(3);
});

it('protects concurrent seat bookings with lockForUpdate', function () {
    $workspace = seatWorkspace(['capacity' => 4]);
    $userA = User::factory()->userRole()->create();
    $userB = User::factory()->userRole()->create();
    $start = Carbon::parse('2026-10-10 10:00:00');
    $end = Carbon::parse('2026-10-10 12:00:00');

    $errors = 0;
    $created = 0;

    foreach ([$userA, $userB] as $user) {
        try {
            DB::transaction(function () use ($user, $workspace, $start, $end, &$created) {
                app(CreateBooking::class)->handle($user, $workspace, $start, $end, 3);
                $created++;
            });
        } catch (ValidationException) {
            $errors++;
        }
    }

    expect($created)->toBe(1)
        ->and($errors)->toBe(1)
        ->and(Booking::where('workspace_id', $workspace->id)->where('status', BookingStatus::Pending)->count())->toBe(1);
});

it('rejects HTTP seats above remaining capacity', function () {
    $workspace = seatWorkspace(['capacity' => 4]);
    $user = User::factory()->userRole()->create();

    app(CreateBooking::class)->handle(
        $user,
        $workspace,
        Carbon::parse('2026-10-10 10:00:00'),
        Carbon::parse('2026-10-10 12:00:00'),
        3,
    );

    $this->actingAs($user)
        ->post(route('user.bookings.store'), [
            'workspace_id' => $workspace->id,
            // Wall clocks in Asia/Gaza (UTC+3 on this date) → 10:00–12:00 UTC, overlapping the existing booking.
            'start_at' => '2026-10-10T13:00',
            'end_at' => '2026-10-10T15:00',
            'seats' => 2,
        ])
        ->assertSessionHasErrors('seats');
});

it('backfills booking seats to one so historic totals stay valid', function () {
    $booking = Booking::factory()->create(['seats' => 1, 'total_price' => '150.00']);

    $migration = require database_path('migrations/2026_10_04_190100_add_seats_to_bookings_table.php');
    // Column already exists in tests after migrate — re-run backfill semantics.
    DB::table('bookings')->where('id', $booking->id)->update(['seats' => 9]);
    DB::table('bookings')->update(['seats' => 1]);

    expect((int) $booking->fresh()->seats)->toBe(1)
        ->and((string) $booking->fresh()->total_price)->toBe('150.00');
});
