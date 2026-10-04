<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Bookings\SeatAvailability;
use App\Services\Pricing\BookingPricingService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBooking
{
    public function __construct(
        private readonly BookingPricingService $pricing,
        private readonly SeatAvailability $seats,
    ) {}

    public function handle(
        User $user,
        Workspace $workspace,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        ?int $requestedSeats = 1,
    ): Booking {
        $start = Carbon::parse($startAt)->seconds(0);
        $end = Carbon::parse($endAt)->seconds(0);

        return DB::transaction(function () use ($user, $workspace, $start, $end, $requestedSeats) {
            /** @var Workspace $locked */
            $locked = Workspace::query()
                ->whereKey($workspace->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertWithinOpeningHours($locked, $start, $end);

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

    private function assertWithinOpeningHours(Workspace $workspace, Carbon $start, Carbon $end): void
    {
        if ($start->toDateString() !== $end->toDateString()) {
            throw ValidationException::withMessages([
                'end_at' => 'Bookings must start and end on the same day.',
            ]);
        }

        $open = Carbon::parse($start->toDateString().' '.$workspace->opening_time);
        $close = Carbon::parse($start->toDateString().' '.$workspace->closing_time);

        if ($start->lt($open) || $end->gt($close)) {
            throw ValidationException::withMessages([
                'start_at' => 'Booking must be within workspace opening hours ('.$open->format('H:i').'–'.$close->format('H:i').').',
            ]);
        }
    }
}
