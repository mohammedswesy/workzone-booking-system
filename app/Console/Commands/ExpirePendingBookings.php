<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Console\Command;

class ExpirePendingBookings extends Command
{
    protected $signature = 'bookings:expire-pending';

    protected $description = 'Cancel pending bookings that exceeded the configured timeout';

    public function handle(): int
    {
        $minutes = (int) config('booking.pending_timeout_minutes', 30);
        $cutoff = now()->subMinutes($minutes);

        $count = Booking::query()
            ->where('status', BookingStatus::Pending)
            ->where('created_at', '<', $cutoff)
            ->update(['status' => BookingStatus::Cancelled]);

        $this->info("Expired {$count} pending booking(s).");

        return self::SUCCESS;
    }
}
