<?php

namespace App\Services\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Audit\AuditLogger;
use App\Services\Ledger\OwnerLedgerService;
use App\Support\AppTimezone;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkBookingPaid
{
    public function __construct(
        private readonly OwnerLedgerService $ledger,
        private readonly PaymentStateMachine $states,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     confirmed_by?: ?int,
     *     confirmed_by_role?: ?string,
     *     received_amount?: string|float|int,
     *     amount_disposition?: ?string,
     *     amount_note?: ?string,
     * }  $payload
     */
    public function handle(Payment $payment, array $payload = []): Payment
    {
        $paid = DB::transaction(function () use ($payment, $payload) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentStatus::Paid) {
                return $locked;
            }

            $this->states->assertCanTransition($locked->status, PaymentStatus::Paid);

            $expected = Money::of($locked->amount);
            $received = Money::of($payload['received_amount'] ?? $expected);
            $cmp = Money::cmp($received, $expected);
            $disposition = $payload['amount_disposition'] ?? null;
            $note = trim((string) ($payload['amount_note'] ?? ''));

            if ($cmp !== 0) {
                if (! in_array($disposition, ['partial', 'overpaid'], true)) {
                    throw ValidationException::withMessages([
                        'received_amount' => 'Received amount differs from expected. Choose partial or overpaid and add a note.',
                    ]);
                }
                if ($note === '') {
                    throw ValidationException::withMessages([
                        'amount_note' => 'A note is required when the received amount differs.',
                    ]);
                }
                if ($disposition === 'partial' && $cmp !== -1) {
                    throw ValidationException::withMessages([
                        'amount_disposition' => 'Partial requires a received amount less than expected.',
                    ]);
                }
                if ($disposition === 'overpaid' && $cmp !== 1) {
                    throw ValidationException::withMessages([
                        'amount_disposition' => 'Overpaid requires a received amount greater than expected.',
                    ]);
                }
            } else {
                $disposition = null;
                $note = $note !== '' ? $note : null;
            }

            // Partial: record attempt but keep booking unpaid (payment stays pending).
            if ($disposition === 'partial') {
                $locked->update([
                    'received_amount' => $received,
                    'amount_disposition' => 'partial',
                    'amount_note' => $note,
                    'metadata' => array_merge($locked->metadata ?? [], [
                        'partial_received_at' => AppTimezone::utcIso(),
                        'partial_received_by' => $payload['confirmed_by'] ?? null,
                        'partial_expected' => $expected,
                        'partial_received' => $received,
                    ]),
                ]);

                $this->audit->log(
                    'payment.partial',
                    actor: request()->user(),
                    subject: $locked,
                    newValues: [
                        'received_amount' => $received,
                        'expected_amount' => $expected,
                        'amount_note' => $note,
                    ],
                );

                return $locked->fresh(['booking']);
            }

            $paidAt = AppTimezone::now();

            $locked->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => $paidAt,
                'received_amount' => $received,
                'amount_disposition' => $disposition,
                'amount_note' => $disposition === 'overpaid'
                    ? trim($note.' | overpayment_diff='.Money::sub($received, $expected))
                    : $note,
                'metadata' => array_merge($locked->metadata ?? [], [
                    'confirmed_by' => $payload['confirmed_by'] ?? null,
                    'confirmed_by_role' => $payload['confirmed_by_role'] ?? 'admin',
                    // Same UTC instant as paid_at — never hand-format a local clock.
                    'confirmed_at' => AppTimezone::utcIso($paidAt),
                    'expected_amount' => $expected,
                    'received_amount' => $received,
                    'amount_disposition' => $disposition,
                ]),
            ]);

            /** @var Booking $booking */
            $booking = Booking::query()->whereKey($locked->booking_id)->lockForUpdate()->firstOrFail();

            $updates = ['payment_status' => PaymentStatus::Paid];
            if ($booking->status === BookingStatus::Pending) {
                $updates['status'] = BookingStatus::Confirmed;
            }

            $booking->update($updates);

            $this->audit->log(
                'payment.confirm',
                actor: request()->user(),
                subject: $locked,
                oldValues: ['status' => PaymentStatus::Pending->value],
                newValues: [
                    'status' => PaymentStatus::Paid->value,
                    'paid_at' => AppTimezone::utcIso($paidAt),
                    'received_amount' => $received,
                    'amount_disposition' => $disposition,
                ],
            );

            return $locked->fresh(['booking']);
        });

        if ($paid->amount_disposition === 'partial' && $paid->status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'received_amount' => 'Partial payment recorded. Booking remains unpaid until the full amount is received.',
            ]);
        }

        if ($paid->booking && $paid->status === PaymentStatus::Paid) {
            $this->ledger->postCompletionEntries($paid->booking);
        }

        return $paid;
    }
}
