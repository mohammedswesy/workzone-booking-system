<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Availability\AvailabilityService;
use App\Services\Bookings\SeatAvailability;
use App\Services\Pricing\BookingPricingService;
use App\Support\AppTimezone;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBooking
{
    public function __construct(
        private readonly BookingPricingService $pricing,
        private readonly SeatAvailability $seats,
        private readonly AvailabilityService $availability,
    ) {}

    public function handle(
        User $user,
        Workspace $workspace,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        ?int $requestedSeats = 1,
    ): Booking {
        // Callers pass UTC instants (already converted from venue-timezone wall clocks).
        $start = Carbon::parse($startAt)->utc()->seconds(0);
        $end = Carbon::parse($endAt)->utc()->seconds(0);

        if ($start->lte(AppTimezone::now())) {
            throw ValidationException::withMessages([
                'start_at' => 'The start time must be in the future.',
            ]);
        }

        if ($end->lte($start)) {
            throw ValidationException::withMessages([
                'end_at' => 'The end time must be after the start time.',
            ]);
        }

        return DB::transaction(function () use ($user, $workspace, $start, $end, $requestedSeats) {
            /** @var Workspace $locked */
            $locked = Workspace::query()
                ->whereKey($workspace->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->availability->assertBookable($locked, $start, $end);

            $seats = $this->seats->resolveSeats($locked, $requestedSeats);
            $this->seats->assertCanBook($locked, $start, $end, $seats);

            $quote = $this->pricing->quote($locked, $start, $end, seats: $seats);
            $minutes = $start->diffInMinutes($end);

            return Booking::create([
                'user_id' => $user->id,
                'workspace_id' => $locked->id,
                'start_at' => $start,
                'end_at' => $end,
                'hours' => max(1, (int) ceil($minutes / 60)),
                'seats' => $seats,
                'total_price' => $quote->finalAmount,
                'status' => BookingStatus::Pending,
                'payment_status' => PaymentStatus::Unpaid,
            ]);
        });
    }

    /**
     * @deprecated Use AvailabilityService::assertBookable — kept for call-site compatibility.
     */
    public function assertWithinOpeningHours(Workspace $workspace, Carbon $startUtc, Carbon $endUtc): void
    {
        $this->availability->assertBookable($workspace, $startUtc, $endUtc);
    }
}
