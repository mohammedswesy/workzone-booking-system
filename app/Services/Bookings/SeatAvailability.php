<?php

namespace App\Services\Bookings;

use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SeatAvailability
{
    public function reservedSeats(
        Workspace $workspace,
        Carbon $start,
        Carbon $end,
        ?int $ignoreBookingId = null,
    ): int {
        $query = Booking::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', BookingStatus::blocking())
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->lockForUpdate();

        if ($ignoreBookingId) {
            $query->whereKeyNot($ignoreBookingId);
        }

        return (int) $query->sum('seats');
    }

    public function remainingSeats(
        Workspace $workspace,
        Carbon $start,
        Carbon $end,
        ?int $ignoreBookingId = null,
    ): int {
        $capacity = max(1, (int) $workspace->capacity);
        $reserved = $this->reservedSeats($workspace, $start, $end, $ignoreBookingId);

        return max(0, $capacity - $reserved);
    }

    public function resolveSeats(Workspace $workspace, ?int $requested): int
    {
        if ($workspace->booking_mode === BookingMode::Whole) {
            return max(1, (int) $workspace->capacity);
        }

        return max(1, (int) ($requested ?? 1));
    }

    public function assertCanBook(
        Workspace $workspace,
        Carbon $start,
        Carbon $end,
        int $seats,
        ?int $ignoreBookingId = null,
    ): void {
        $capacity = max(1, (int) $workspace->capacity);
        $seats = max(1, $seats);

        if ($workspace->booking_mode === BookingMode::Whole) {
            $overlap = Booking::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('status', BookingStatus::blocking())
                ->where('start_at', '<', $end)
                ->where('end_at', '>', $start)
                ->when($ignoreBookingId, fn ($q) => $q->whereKeyNot($ignoreBookingId))
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'start_at' => 'This workspace is already booked for the selected time range.',
                ]);
            }

            return;
        }

        if ($seats > $capacity) {
            throw ValidationException::withMessages([
                'seats' => 'Requested seats exceed workspace capacity.',
            ]);
        }

        $remaining = $this->remainingSeats($workspace, $start, $end, $ignoreBookingId);

        if ($seats > $remaining) {
            throw ValidationException::withMessages([
                'seats' => 'Only '.$remaining.' seat(s) remain for the selected time range.',
            ]);
        }
    }
}
