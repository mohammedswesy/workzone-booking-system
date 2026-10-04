<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Pricing\BookingPricingService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBooking
{
    public function __construct(
        private readonly BookingPricingService $pricing,
    ) {}

    public function handle(User $user, Workspace $workspace, CarbonInterface $startAt, CarbonInterface $endAt): Booking
    {
        $start = Carbon::parse($startAt)->seconds(0);
        $end = Carbon::parse($endAt)->seconds(0);

        return DB::transaction(function () use ($user, $workspace, $start, $end) {
            /** @var Workspace $locked */
            $locked = Workspace::query()
                ->whereKey($workspace->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertWithinOpeningHours($locked, $start, $end);
            $this->assertNoOverlap($locked, $start, $end);

            $quote = $this->pricing->quote($locked, $start, $end);
            $minutes = $start->diffInMinutes($end);

            return Booking::create([
                'user_id' => $user->id,
                'workspace_id' => $locked->id,
                'start_at' => $start,
                'end_at' => $end,
                'hours' => max(1, (int) ceil($minutes / 60)),
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

    private function assertNoOverlap(Workspace $workspace, Carbon $start, Carbon $end): void
    {
        $overlap = Booking::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', BookingStatus::blocking())
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->lockForUpdate()
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_at' => 'This workspace is already booked for the selected time range.',
            ]);
        }
    }
}
