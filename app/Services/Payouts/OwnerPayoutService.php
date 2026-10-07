<?php

namespace App\Services\Payouts;

use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayout;
use App\Models\User;
use App\Services\Ledger\OwnerLedgerService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnerPayoutService
{
    public function __construct(
        private readonly OwnerLedgerService $ledger,
    ) {}

    public function request(User $owner, string $amount, ?string $note = null): OwnerPayout
    {
        return DB::transaction(function () use ($owner, $amount, $note) {
            User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();

            if (OwnerPayout::query()
                ->where('owner_id', $owner->id)
                ->whereIn('status', [PayoutStatus::Requested, PayoutStatus::Approved])
                ->exists()) {
                throw ValidationException::withMessages([
                    'amount' => 'You already have an open payout request.',
                ]);
            }

            if (! filled($owner->payout_method) || ! filled($owner->payout_account_identifier)) {
                throw ValidationException::withMessages([
                    'payout_method' => 'Add your payout details on your profile before requesting a payout.',
                ]);
            }

            $amount = Money::of($amount);
            if (Money::cmp($amount, '0') !== 1) {
                throw ValidationException::withMessages([
                    'amount' => 'Payout amount must be greater than zero.',
                ]);
            }

            $balances = $this->ledger->balancesFor($owner);
            if (Money::cmp($amount, $balances['available']) === 1) {
                throw ValidationException::withMessages([
                    'amount' => 'Payout cannot exceed your available balance ('.$balances['available'].').',
                ]);
            }

            return OwnerPayout::create([
                'owner_id' => $owner->id,
                'amount_requested' => $amount,
                'currency' => $balances['currency'],
                'status' => PayoutStatus::Requested,
                'payout_method' => $owner->payout_method,
                'payout_account_holder' => $owner->payout_account_holder,
                'payout_account_identifier' => $owner->payout_account_identifier,
                'owner_note' => $note,
            ]);
        });
    }

    public function approve(OwnerPayout $payout, ?string $amountApproved, ?string $adminNote, User $admin): OwnerPayout
    {
        return DB::transaction(function () use ($payout, $amountApproved, $adminNote, $admin) {
            /** @var OwnerPayout $locked */
            $locked = OwnerPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            User::query()->whereKey($locked->owner_id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PayoutStatus::Requested) {
                throw ValidationException::withMessages([
                    'status' => 'Only requested payouts can be approved.',
                ]);
            }

            $amount = Money::of($amountApproved ?: $locked->amount_requested);
            $balances = $this->ledger->balancesFor($locked->owner()->firstOrFail());
            if (Money::cmp($amount, $balances['available']) === 1) {
                throw ValidationException::withMessages([
                    'amount_approved' => 'Approved amount exceeds available balance.',
                ]);
            }

            $locked->update([
                'status' => PayoutStatus::Approved,
                'amount_approved' => $amount,
                'admin_note' => $adminNote,
                'reviewed_by' => $admin->id,
            ]);

            return $locked->fresh();
        });
    }

    public function markPaid(
        OwnerPayout $payout,
        string $transferReference,
        string $paidAt,
        ?string $adminNote,
        User $admin,
        ?string $payoutMethod = null,
    ): OwnerPayout {
        return DB::transaction(function () use ($payout, $transferReference, $paidAt, $adminNote, $admin, $payoutMethod) {
            /** @var OwnerPayout $locked */
            $locked = OwnerPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            User::query()->whereKey($locked->owner_id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [PayoutStatus::Requested, PayoutStatus::Approved], true)) {
                throw ValidationException::withMessages([
                    'status' => 'This payout cannot be marked paid.',
                ]);
            }

            if (OwnerLedgerEntry::query()
                ->where('payout_id', $locked->id)
                ->where('type', LedgerEntryType::Payout)
                ->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'This payout was already recorded in the ledger.',
                ]);
            }

            $amount = Money::of($locked->amount_approved ?? $locked->amount_requested);
            $balances = $this->ledger->balancesFor($locked->owner()->firstOrFail());
            if (Money::cmp($amount, $balances['available']) === 1) {
                throw ValidationException::withMessages([
                    'amount' => 'Cannot pay more than the available balance.',
                ]);
            }

            $locked->update([
                'status' => PayoutStatus::Paid,
                'amount_approved' => $amount,
                'payout_method' => $payoutMethod ?: $locked->payout_method,
                'transfer_reference' => $transferReference,
                'paid_at' => $paidAt,
                'admin_note' => $adminNote ?? $locked->admin_note,
                'reviewed_by' => $admin->id,
            ]);

            try {
                OwnerLedgerEntry::query()->firstOrCreate(
                    ['idempotency_key' => 'payout:'.$locked->id],
                    [
                        'owner_id' => $locked->owner_id,
                        'payout_id' => $locked->id,
                        'type' => LedgerEntryType::Payout,
                        'amount' => Money::neg($amount),
                        'currency' => $locked->currency,
                        'status' => 'posted',
                        'note' => 'Payout #'.$locked->id.' paid ('.$transferReference.')',
                        'created_by' => $admin->id,
                    ],
                );
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // Already posted under a race — treat as paid once.
            }

            return $locked->fresh();
        });
    }

    public function reject(OwnerPayout $payout, string $reason, User $admin): OwnerPayout
    {
        return DB::transaction(function () use ($payout, $reason, $admin) {
            /** @var OwnerPayout $locked */
            $locked = OwnerPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [PayoutStatus::Requested, PayoutStatus::Approved], true)) {
                throw ValidationException::withMessages([
                    'status' => 'This payout cannot be rejected.',
                ]);
            }

            $locked->update([
                'status' => PayoutStatus::Rejected,
                'rejection_reason' => $reason,
                'reviewed_by' => $admin->id,
            ]);

            return $locked->fresh();
        });
    }
}
