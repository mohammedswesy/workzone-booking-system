<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Models\Booking;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Rules\TransferReferenceRule;
use App\Services\Ledger\OwnerLedgerService;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PaymentsReconcileCommand extends Command
{
    protected $signature = 'payments:reconcile {--json : Output machine-readable JSON}';

    protected $description = 'Reconcile payment, ledger, and payout invariants';

    public function handle(OwnerLedgerService $ledger): int
    {
        $issues = [];

        // Paid payments without earning after booking completed (post-cutover).
        $cutover = $ledger->cutoverAt();
        Booking::query()
            ->where('status', BookingStatus::Completed)
            ->where('payment_status', PaymentStatus::Paid)
            ->whereHas('payments', function ($q) use ($cutover) {
                $q->where('status', PaymentStatus::Paid)
                    ->whereNotNull('paid_at')
                    ->where('paid_at', '>=', $cutover);
            })
            ->whereDoesntHave('ledgerEntries', fn ($q) => $q->where('type', LedgerEntryType::Earning))
            ->pluck('id')
            ->each(function ($id) use (&$issues) {
                $issues[] = [
                    'code' => 'paid_completed_without_earning',
                    'booking_id' => $id,
                ];
            });

        // Ledger earnings without a paid payment.
        OwnerLedgerEntry::query()
            ->where('type', LedgerEntryType::Earning)
            ->whereNotNull('booking_id')
            ->whereDoesntHave('booking.payments', fn ($q) => $q->where('status', PaymentStatus::Paid))
            ->pluck('id')
            ->each(function ($id) use (&$issues) {
                $issues[] = [
                    'code' => 'earning_without_paid_payment',
                    'ledger_entry_id' => $id,
                ];
            });

        // Booking total vs payment amount mismatches (paid).
        Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->with('booking:id,total_price')
            ->get()
            ->each(function (Payment $payment) use (&$issues) {
                if (! $payment->booking) {
                    return;
                }
                if (Money::cmp($payment->amount, $payment->booking->total_price) !== 0) {
                    $issues[] = [
                        'code' => 'booking_payment_amount_mismatch',
                        'payment_id' => $payment->id,
                        'booking_id' => $payment->booking_id,
                        'payment_amount' => Money::of($payment->amount),
                        'booking_total' => Money::of($payment->booking->total_price),
                    ];
                }
            });

        // Payouts whose approved amount exceeds available balance snapshot (best-effort).
        OwnerPayout::query()
            ->whereIn('status', [PayoutStatus::Approved, PayoutStatus::Paid])
            ->with('owner')
            ->get()
            ->each(function (OwnerPayout $payout) use (&$issues, $ledger) {
                if (! $payout->owner) {
                    return;
                }
                // Historical: compare approved amount to current balance + this payout amount.
                $balances = $ledger->balancesFor($payout->owner);
                $reconstructed = Money::add($balances['balance'], Money::of($payout->amount));
                if (Money::cmp(Money::of($payout->amount), $reconstructed) === 1
                    && Money::cmp($balances['balance'], '0') === -1) {
                    $issues[] = [
                        'code' => 'payout_exceeds_available_at_approval',
                        'payout_id' => $payout->id,
                    ];
                }
            });

        // Negative balances.
        OwnerLedgerEntry::query()
            ->selectRaw('owner_id, SUM(amount) as balance')
            ->where('status', 'posted')
            ->groupBy('owner_id')
            ->havingRaw('SUM(amount) < 0')
            ->get()
            ->each(function ($row) use (&$issues) {
                $issues[] = [
                    'code' => 'negative_owner_balance',
                    'owner_id' => $row->owner_id,
                    'balance' => Money::of($row->balance),
                ];
            });

        // Duplicate references (case-insensitive per method).
        Payment::query()
            ->whereNotNull('transfer_reference')
            ->whereNotNull('platform_payment_method_id')
            ->selectRaw('platform_payment_method_id, LOWER(transfer_reference) as ref, COUNT(*) as c')
            ->groupBy('platform_payment_method_id', 'ref')
            ->having('c', '>', 1)
            ->get()
            ->each(function ($row) use (&$issues) {
                $issues[] = [
                    'code' => 'duplicate_transfer_reference',
                    'platform_payment_method_id' => $row->platform_payment_method_id,
                    'transfer_reference' => $row->ref,
                    'count' => (int) $row->c,
                ];
            });

        // Invalid existing references (report only).
        Payment::query()
            ->whereNotNull('transfer_reference')
            ->get(['id', 'transfer_reference'])
            ->each(function (Payment $payment) use (&$issues) {
                $reasons = TransferReferenceRule::invalidReasons((string) $payment->transfer_reference);
                if ($reasons !== []) {
                    $issues[] = [
                        'code' => 'invalid_transfer_reference',
                        'payment_id' => $payment->id,
                        'transfer_reference' => $payment->transfer_reference,
                        'reasons' => $reasons,
                    ];
                }
            });

        // Venue / unit owner denormalization drift.
        if (\Illuminate\Support\Facades\Schema::hasColumn('workspaces', 'venue_id')) {
            \App\Models\Workspace::query()
                ->whereNotNull('venue_id')
                ->whereColumn('workspaces.owner_id', '!=', 'venues.owner_id')
                ->join('venues', 'venues.id', '=', 'workspaces.venue_id')
                ->pluck('workspaces.id')
                ->each(function ($id) use (&$issues) {
                    $issues[] = [
                        'code' => 'workspace_venue_owner_mismatch',
                        'workspace_id' => $id,
                    ];
                });
        }

        // Pending proofs older than 48h.
        Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->where('created_at', '<', Carbon::now('UTC')->subHours(48))
            ->pluck('id')
            ->each(function ($id) use (&$issues) {
                $issues[] = [
                    'code' => 'pending_proof_older_than_48h',
                    'payment_id' => $id,
                ];
            });

        // Ledger sum vs sum of type totals.
        $byType = OwnerLedgerEntry::query()
            ->where('status', 'posted')
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $sumTypes = '0.00';
        foreach ($byType as $total) {
            $sumTypes = Money::add($sumTypes, $total);
        }
        $sumAll = Money::of(
            OwnerLedgerEntry::query()->where('status', 'posted')->sum('amount')
        );
        if (Money::cmp($sumTypes, $sumAll) !== 0) {
            $issues[] = [
                'code' => 'ledger_sum_mismatch',
                'sum_by_type' => $sumTypes,
                'sum_all' => $sumAll,
            ];
        }

        $ok = $issues === [];
        $payload = [
            'ok' => $ok,
            'checked_at' => Carbon::now('UTC')->toIso8601String(),
            'issue_count' => count($issues),
            'issues' => $issues,
        ];

        Cache::put('payments.reconcile.last', $payload, now()->addDays(7));

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT));
        } else {
            if ($ok) {
                $this->info('Reconciliation OK — no invariant breaks.');
            } else {
                $this->error('Reconciliation FAILED — '.count($issues).' issue(s):');
                foreach ($issues as $issue) {
                    $this->line('- '.json_encode($issue));
                }
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
