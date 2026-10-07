<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\AppTimezone;
use Illuminate\Console\Command;

class ExpirePendingBookings extends Command
{
    protected $signature = 'bookings:expire-pending';

    protected $description = 'Cancel pending bookings that exceeded the configured timeout';

    public function handle(): int
    {
        $minutes = (int) config('booking.pending_timeout_minutes', 30);
        // Use app display clock (Asia/Gaza by default) so expiry aligns with booking wall times.
        $cutoff = AppTimezone::now()->subMinutes($minutes);

        $count = Booking::query()
            ->where('status', BookingStatus::Pending)
            ->where('created_at', '<', $cutoff)
            ->update(['status' => BookingStatus::Cancelled]);

        $this->info("Expired {$count} pending booking(s).");

        return self::SUCCESS;
    }
}
