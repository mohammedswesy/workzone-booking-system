<?php

namespace App\Services\Ledger;

use App\Enums\BookingStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Models\Booking;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayout;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnerLedgerService
{
    /**
     * @return array{balance: string, available: string, pending: string, currency: string}
     */
    public function balancesFor(User $owner): array
    {
        $currency = (string) config('payments.currency', 'USD');
        $balance = Money::of(
            OwnerLedgerEntry::query()
                ->where('owner_id', $owner->id)
                ->where('status', 'posted')
                ->sum('amount')
        );

        $pending = $this->pendingEarningsFor($owner);
        // Requestable amount never goes negative; ledger balance may (deficit carries).
        $available = Money::cmp($balance, '0') === -1 ? '0.00' : $balance;

        return [
            'balance' => $balance,
            'available' => $available,
            'pending' => $pending,
            'currency' => $currency,
        ];
    }

    public function cutoverAt(): CarbonInterface
    {
        $fromSetting = PlatformSetting::getValue('ledger_cutover_at');
        $raw = filled($fromSetting)
            ? (string) $fromSetting
            : (string) config('payments.ledger.cutover_at', '2026-10-04 00:00:00');

        return Carbon::parse($raw);
    }

    public function paymentQualifiesForLedger(Booking $booking): bool
    {
        $paidAt = $booking->payments()
            ->where('status', PaymentStatus::Paid)
            ->whereNotNull('paid_at')
            ->orderByDesc('paid_at')
            ->value('paid_at');

        if (! $paidAt) {
            return false;
        }

        return Carbon::parse($paidAt)->gte($this->cutoverAt());
    }

    public function pendingEarningsFor(User $owner): string
    {
        if (! config('payments.ledger.available_requires_completed', true)) {
            return '0.00';
        }

        $cutover = $this->cutoverAt();

        $bookings = Booking::query()
            ->whereHas('workspace', fn ($q) => $q->where('owner_id', $owner->id))
            ->where('payment_status', PaymentStatus::Paid)
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->whereDoesntHave('ledgerEntries', fn ($q) => $q->where('type', LedgerEntryType::Earning))
            ->whereHas('payments', function ($q) use ($cutover) {
                $q->where('status', PaymentStatus::Paid)
                    ->whereNotNull('paid_at')
                    ->where('paid_at', '>=', $cutover);
            })
            ->get(['id', 'total_price', 'workspace_id']);

        $total = '0.00';
        foreach ($bookings as $booking) {
            $net = $this->ownerNetForBooking($owner, Money::of($booking->total_price));
            $total = Money::add($total, $net);
        }

        return $total;
    }

    public function commissionPercentFor(User $owner): string
    {
        if ($owner->commission_percent !== null) {
            return Money::of($owner->commission_percent);
        }

        return PlatformSetting::commissionPercent();
    }

    /**
     * @return array{earning: string, commission: string, net: string}
     */
    public function split(User $owner, string $gross): array
    {
        $gross = Money::of($gross);
        $percent = $this->commissionPercentFor($owner);
        $commission = Money::percentOf($gross, $percent);

        return [
            'earning' => $gross,
            'commission' => Money::neg($commission),
            'net' => Money::sub($gross, $commission),
        ];
    }

    public function ownerNetForBooking(User $owner, string $gross): string
    {
        return $this->split($owner, $gross)['net'];
    }

    public function postCompletionEntries(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $booking->loadMissing(['workspace.owner', 'payments']);

            if ($booking->payment_status !== PaymentStatus::Paid) {
                return;
            }

            if ($booking->status !== BookingStatus::Completed) {
                return;
            }

            if (! $this->paymentQualifiesForLedger($booking)) {
                return;
            }

            $owner = $booking->workspace?->owner;
            if (! $owner) {
                return;
            }

            $split = $this->split($owner, Money::of($booking->total_price));
            $currency = (string) config('payments.currency', 'USD');

            $this->createUniqueEntry([
                'idempotency_key' => 'earning:'.$booking->id,
                'owner_id' => $owner->id,
                'booking_id' => $booking->id,
                'type' => LedgerEntryType::Earning,
                'amount' => $split['earning'],
                'currency' => $currency,
                'status' => 'posted',
                'note' => 'Booking #'.$booking->id.' earning',
            ]);

            $this->createUniqueEntry([
                'idempotency_key' => 'commission:'.$booking->id,
                'owner_id' => $owner->id,
                'booking_id' => $booking->id,
                'type' => LedgerEntryType::Commission,
                'amount' => $split['commission'],
                'currency' => $currency,
                'status' => 'posted',
                'note' => 'Booking #'.$booking->id.' commission '.$this->commissionPercentFor($owner).'%',
            ]);
        });
    }

    public function postRefundReversal(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $booking->loadMissing('workspace.owner');
            $owner = $booking->workspace?->owner;
            if (! $owner) {
                return;
            }

            $posted = OwnerLedgerEntry::query()
                ->where('booking_id', $booking->id)
                ->whereIn('type', [LedgerEntryType::Earning, LedgerEntryType::Commission])
                ->get();

            if ($posted->isEmpty()) {
                return;
            }

            $currency = (string) config('payments.currency', 'USD');

            foreach ($posted as $entry) {
                $typeValue = $entry->type instanceof LedgerEntryType
                    ? $entry->type->value
                    : (string) $entry->type;

                $this->createUniqueEntry([
                    'idempotency_key' => 'refund:'.$typeValue.':'.$booking->id,
                    'owner_id' => $owner->id,
                    'booking_id' => $booking->id,
                    'type' => LedgerEntryType::Refund,
                    'amount' => Money::neg((string) $entry->amount),
                    'currency' => $currency,
                    'status' => 'posted',
                    'note' => 'Refund reversal for booking #'.$booking->id.' ('.$typeValue.')',
                ]);
            }

            if ($booking->payment_status === PaymentStatus::Paid) {
                $booking->update(['payment_status' => PaymentStatus::Refunded]);
            }
        });
    }

    public function postAdjustment(User $owner, string $amount, string $note, ?User $actor = null): OwnerLedgerEntry
    {
        $note = trim($note);
        if ($note === '') {
            throw ValidationException::withMessages([
                'note' => 'A note is required for ledger adjustments.',
            ]);
        }

        return OwnerLedgerEntry::create([
            'owner_id' => $owner->id,
            'type' => LedgerEntryType::Adjustment,
            'amount' => Money::of($amount),
            'currency' => (string) config('payments.currency', 'USD'),
            'status' => 'posted',
            'note' => $note,
            'created_by' => $actor?->id,
            'idempotency_key' => 'adjustment:'.$owner->id.':'.uniqid('', true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    /**
     * @return array{
     *   balance: string,
     *   available: string,
     *   pending: string,
     *   currency: string,
     *   earnings: string,
     *   commission_taken: string,
     *   refunds: string,
     *   paid_out: string,
     *   adjustments: string
     * }
     */
    public function accountSnapshot(User $owner, ?string $from = null, ?string $to = null): array
    {
        $balances = $this->balancesFor($owner);
        $totals = $this->totalsByType($owner->id, $from, $to);

        return [
            ...$balances,
            'earnings' => $totals['earning'],
            'commission_taken' => Money::neg($totals['commission']),
            'refunds' => $totals['refund'],
            'paid_out' => Money::neg($totals['payout']),
            'adjustments' => $totals['adjustment'],
        ];
    }

    /**
     * Platform-wide ledger totals — single source for admin dashboard / reports.
     *
     * @return array{owed_to_owners: string, commission_earned: string, paid_out: string, currency: string}
     */
    public function platformTotals(): array
    {
        $currency = (string) config('payments.currency', 'USD');

        return [
            'owed_to_owners' => Money::of(
                OwnerLedgerEntry::query()->where('status', 'posted')->sum('amount')
            ),
            'commission_earned' => Money::neg(Money::of(
                OwnerLedgerEntry::query()
                    ->where('status', 'posted')
                    ->where('type', LedgerEntryType::Commission)
                    ->sum('amount')
            )),
            'paid_out' => Money::neg(Money::of(
                OwnerLedgerEntry::query()
                    ->where('status', 'posted')
                    ->where('type', LedgerEntryType::Payout)
                    ->sum('amount')
            )),
            'currency' => $currency,
        ];
    }

    /**
     * @return array{earning: string, commission: string, refund: string, payout: string, adjustment: string}
     */
    public function totalsByType(int $ownerId, ?string $from = null, ?string $to = null): array
    {
        $rows = OwnerLedgerEntry::query()
            ->where('owner_id', $ownerId)
            ->where('status', 'posted')
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $out = [
            'earning' => '0.00',
            'commission' => '0.00',
            'refund' => '0.00',
            'payout' => '0.00',
            'adjustment' => '0.00',
        ];

        foreach ($rows as $type => $total) {
            $key = $type instanceof LedgerEntryType ? $type->value : (string) $type;
            if (array_key_exists($key, $out)) {
                $out[$key] = Money::of($total);
            }
        }

        return $out;
    }

    /**
     * @return list<array{workspace_id: int|null, workspace_name: string, bookings_count: int, gross: string, commission: string, refunds: string, net: string}>
     */
    public function workspaceBreakdown(User $owner, ?string $from = null, ?string $to = null): array
    {
        $entries = OwnerLedgerEntry::query()
            ->where('owner_id', $owner->id)
            ->where('status', 'posted')
            ->whereNotNull('booking_id')
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->with(['booking:id,workspace_id', 'booking.workspace:id,name'])
            ->get(['id', 'booking_id', 'type', 'amount']);

        $buckets = [];
        foreach ($entries as $entry) {
            $workspaceId = $entry->booking?->workspace_id;
            $key = $workspaceId === null ? 'none' : (string) $workspaceId;
            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'workspace_id' => $workspaceId,
                    'workspace_name' => $entry->booking?->workspace?->name ?? '—',
                    'booking_ids' => [],
                    'gross' => '0.00',
                    'commission' => '0.00',
                    'refunds' => '0.00',
                    'net' => '0.00',
                ];
            }

            if ($entry->booking_id) {
                $buckets[$key]['booking_ids'][$entry->booking_id] = true;
            }

            $amount = Money::of($entry->amount);
            $buckets[$key]['net'] = Money::add($buckets[$key]['net'], $amount);

            $type = $entry->type instanceof LedgerEntryType ? $entry->type : LedgerEntryType::from((string) $entry->type);
            if ($type === LedgerEntryType::Earning) {
                $buckets[$key]['gross'] = Money::add($buckets[$key]['gross'], $amount);
            } elseif ($type === LedgerEntryType::Commission) {
                $buckets[$key]['commission'] = Money::add($buckets[$key]['commission'], Money::neg($amount));
            } elseif ($type === LedgerEntryType::Refund) {
                $buckets[$key]['refunds'] = Money::add($buckets[$key]['refunds'], $amount);
            }
        }

        $rows = [];
        foreach ($buckets as $bucket) {
            $rows[] = [
                'workspace_id' => $bucket['workspace_id'],
                'workspace_name' => $bucket['workspace_name'],
                'bookings_count' => count($bucket['booking_ids']),
                'gross' => $bucket['gross'],
                'commission' => $bucket['commission'],
                'refunds' => $bucket['refunds'],
                'net' => $bucket['net'],
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['workspace_name'], $b['workspace_name']));

        return $rows;
    }

    public function openPayoutFor(User $owner): ?OwnerPayout
    {
        return OwnerPayout::query()
            ->where('owner_id', $owner->id)
            ->whereIn('status', [PayoutStatus::Requested, PayoutStatus::Approved])
            ->latest()
            ->first();
    }

    private function createUniqueEntry(array $attributes): OwnerLedgerEntry
    {
        try {
            return OwnerLedgerEntry::query()->firstOrCreate(
                ['idempotency_key' => $attributes['idempotency_key']],
                $attributes,
            );
        } catch (UniqueConstraintViolationException) {
            return OwnerLedgerEntry::query()
                ->where('idempotency_key', $attributes['idempotency_key'])
                ->firstOrFail();
        }
    }
}
