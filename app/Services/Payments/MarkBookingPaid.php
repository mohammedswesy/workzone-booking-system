<?php

namespace App\Services\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class MarkBookingPaid
{
    public function handle(Payment $payment, array $metadata = []): Payment
    {
        return DB::transaction(function () use ($payment, $metadata) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentStatus::Paid) {
                return $locked;
            }

            $locked->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'metadata' => array_merge($locked->metadata ?? [], $metadata),
            ]);

            /** @var Booking $booking */
            $booking = Booking::query()->whereKey($locked->booking_id)->lockForUpdate()->firstOrFail();

            $booking->update([
                'payment_status' => PaymentStatus::Paid,
                'status' => $booking->status === BookingStatus::Cancelled
                    ? $booking->status
                    : BookingStatus::Confirmed,
            ]);

            return $locked->fresh(['booking']);
        });
    }
}
