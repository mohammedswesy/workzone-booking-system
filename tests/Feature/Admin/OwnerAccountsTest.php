<?php

use App\Enums\BookingStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Models\Booking;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ledger\OwnerLedgerService;
use App\Support\Money;

it('keeps owner accounts, payouts, and dashboard on the same ledger numbers', function () {
    config(['payments.ledger.cutover_at' => '2026-10-04 00:00:00']);

    $admin = User::factory()->admin()->create();
    $owner = User::factory()->owner()->create([
        'name' => 'Ledger Owner',
        'commission_percent' => null,
    ]);
    $other = User::factory()->owner()->create();
    $user = User::factory()->userRole()->create();

    $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'name' => 'Alpha Desk']);
    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Completed,
        'payment_status' => PaymentStatus::Paid,
        'total_price' => '100.00',
    ]);
    Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'oa-ledger-1',
        'amount' => '100.00',
        'currency' => 'USD',
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    \App\Models\PlatformSetting::setValue('commission_percent', '10.00');
    $ledger = app(OwnerLedgerService::class);
    $ledger->postCompletionEntries($booking->fresh());

    OwnerLedgerEntry::create([
        'idempotency_key' => 'payout:oa-test-1',
        'owner_id' => $owner->id,
        'type' => LedgerEntryType::Payout,
        'amount' => '-40.00',
        'currency' => 'USD',
        'status' => 'posted',
        'note' => 'partial payout',
    ]);

    $open = OwnerPayout::create([
        'owner_id' => $owner->id,
        'amount_requested' => '20.00',
        'currency' => 'USD',
        'status' => PayoutStatus::Requested,
        'payout_method' => 'bank_transfer',
        'payout_account_identifier' => 'PS00',
    ]);

    $snapshot = $ledger->accountSnapshot($owner);
    $balances = $ledger->balancesFor($owner);
    $platform = $ledger->platformTotals();

    expect($snapshot['earnings'])->toBe('100.00')
        ->and($snapshot['commission_taken'])->toBe('10.00')
        ->and($snapshot['paid_out'])->toBe('40.00')
        ->and($snapshot['available'])->toBe($balances['available'])
        ->and($snapshot['pending'])->toBe($balances['pending'])
        ->and($snapshot['balance'])->toBe($balances['balance'])
        ->and($snapshot['balance'])->toBe('50.00');

    $this->actingAs($admin)
        ->get(route('admin.owner-accounts.show', $owner))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/OwnerAccounts/Show')
            ->where('snapshot.earnings', '100.00')
            ->where('snapshot.commission_taken', '10.00')
            ->where('snapshot.paid_out', '40.00')
            ->where('snapshot.available', $balances['available'])
            ->where('snapshot.pending', $balances['pending'])
            ->where('snapshot.balance', $balances['balance'])
            ->where('openPayout.id', $open->id)
            ->where('workspaceBreakdown.0.workspace_name', 'Alpha Desk')
            ->where('workspaceBreakdown.0.gross', '100.00')
            ->where('workspaceBreakdown.0.commission', '10.00')
        );

    $this->actingAs($admin)
        ->get(route('admin.payouts.show', $open))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('balances.available', $balances['available'])
            ->where('balances.pending', $balances['pending'])
            ->where('balances.balance', $balances['balance'])
        );

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.owed_to_owners', $platform['owed_to_owners'])
            ->where('stats.commission_earned', $platform['commission_earned'])
            ->where('stats.paid_out', $platform['paid_out'])
        );

    // Platform totals equal the sum of every owner's ledger-derived snapshot.
    $allOwners = User::query()->where('role', 'owner')->get();
    $sumBalance = '0.00';
    $sumCommission = '0.00';
    $sumPaidOut = '0.00';
    foreach ($allOwners as $row) {
        $s = $ledger->accountSnapshot($row);
        $sumBalance = Money::add($sumBalance, $s['balance']);
        $sumCommission = Money::add($sumCommission, $s['commission_taken']);
        $sumPaidOut = Money::add($sumPaidOut, $s['paid_out']);
    }

    expect($platform['owed_to_owners'])->toBe($sumBalance)
        ->and($platform['commission_earned'])->toBe($sumCommission)
        ->and($platform['paid_out'])->toBe($sumPaidOut);

    $this->actingAs($owner)->get(route('admin.owner-accounts.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.owner-accounts.show', $owner))->assertForbidden();
    $this->actingAs($other)->get(route('admin.owner-accounts.summary', $owner))->assertForbidden();
    $this->actingAs($user)->get(route('admin.owner-accounts.export', $owner))->assertForbidden();
});

it('exports and prints owner account summaries for admins', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->owner()->create();
    OwnerLedgerEntry::create([
        'idempotency_key' => 'adj:export-1',
        'owner_id' => $owner->id,
        'type' => LedgerEntryType::Adjustment,
        'amount' => '5.00',
        'currency' => 'USD',
        'status' => 'posted',
        'note' => 'seed',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.owner-accounts.summary', ['owner' => $owner, 'from' => now()->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/OwnerAccounts/Summary'));

    $csv = $this->actingAs($admin)
        ->get(route('admin.owner-accounts.export', ['owner' => $owner, 'locale' => 'en']))
        ->assertOk()
        ->streamedContent();

    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and($csv)->toContain('Adjustment');
});
