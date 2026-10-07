<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayout;
use App\Models\User;
use App\Services\Ledger\OwnerLedgerService;
use App\Services\Payouts\OwnerPayoutService;
use App\Support\Csv\PayoutStatementCsv;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayoutController extends Controller
{
    public function __construct(
        private readonly OwnerPayoutService $payouts,
        private readonly OwnerLedgerService $ledger,
    ) {}

    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $ownerId = $request->integer('owner_id') ?: null;
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        $payouts = OwnerPayout::query()
            ->with('owner:id,name,email')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Payouts/Index', [
            'payouts' => $payouts,
            'filters' => [
                'status' => $status,
                'owner_id' => $ownerId,
                'from' => $from,
                'to' => $to,
            ],
            'owners' => User::query()->where('role', 'owner')->orderBy('name')->get(['id', 'name', 'email']),
            'statuses' => PayoutStatus::values(),
        ]);
    }

    public function show(OwnerPayout $payout)
    {
        $payout->load(['owner:id,name,email,payout_method,payout_account_holder,payout_account_identifier,payout_note']);
        $balances = $this->ledger->balancesFor($payout->owner);

        return Inertia::render('Admin/Payouts/Show', [
            'payout' => $payout,
            'balances' => $balances,
        ]);
    }

    public function approve(Request $request, OwnerPayout $payout)
    {
        $data = $request->validate([
            'amount_approved' => ['nullable', 'numeric', 'min:0.01'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->payouts->approve(
            $payout,
            isset($data['amount_approved']) ? (string) $data['amount_approved'] : null,
            $data['admin_note'] ?? null,
            $request->user(),
        );

        return back()->with('success', 'Payout approved.');
    }

    public function pay(Request $request, OwnerPayout $payout)
    {
        $data = $request->validate([
            'payout_method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'transfer_reference' => ['required', 'string', 'max:120'],
            'paid_at' => ['required', 'date'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->payouts->markPaid(
            $payout,
            $data['transfer_reference'],
            $data['paid_at'],
            $data['admin_note'] ?? null,
            $request->user(),
            $data['payout_method'],
        );

        return back()->with('success', 'Payout marked as paid.');
    }

    public function statement(OwnerPayout $payout)
    {
        $payout->load('owner:id,name,email');
        $entries = OwnerLedgerEntry::query()
            ->where('owner_id', $payout->owner_id)
            ->where(function ($q) use ($payout) {
                $q->where('payout_id', $payout->id)
                    ->orWhere(function ($inner) use ($payout) {
                        $inner->whereNotNull('booking_id')
                            ->where('created_at', '<=', $payout->paid_at ?? $payout->updated_at);
                    });
            })
            ->with(['booking.workspace:id,name'])
            ->orderBy('created_at')
            ->get();

        return Inertia::render('Owner/Payouts/Statement', [
            'payout' => $payout,
            'entries' => $entries,
        ]);
    }

    public function exportCsv(OwnerPayout $payout): StreamedResponse
    {
        return PayoutStatementCsv::download($payout);
    }

    public function reject(Request $request, OwnerPayout $payout)
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->payouts->reject($payout, $data['rejection_reason'], $request->user());

        return back()->with('success', 'Payout rejected.');
    }

    public function adjust(Request $request, User $owner)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric'],
            'note' => ['required', 'string', 'max:1000'],
        ]);

        abort_unless($owner->isOwner(), 404);

        $this->ledger->postAdjustment($owner, (string) $data['amount'], $data['note'], $request->user());

        return back()->with('success', 'Ledger adjustment posted.');
    }
}
