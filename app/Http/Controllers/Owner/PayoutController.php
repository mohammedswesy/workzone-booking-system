<?php

namespace App\Http\Controllers\Owner;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayout;
use App\Services\Ledger\OwnerLedgerService;
use App\Services\Payouts\OwnerPayoutService;
use App\Support\Csv\PayoutStatementCsv;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayoutController extends Controller
{
    public function __construct(
        private readonly OwnerLedgerService $ledger,
        private readonly OwnerPayoutService $payouts,
    ) {}

    public function index(Request $request)
    {
        $owner = $request->user();
        $balances = $this->ledger->balancesFor($owner);

        return Inertia::render('Owner/Payouts/Index', [
            'balances' => $balances,
            'openRequest' => OwnerPayout::query()
                ->where('owner_id', $owner->id)
                ->whereIn('status', [PayoutStatus::Requested, PayoutStatus::Approved])
                ->latest()
                ->first(),
            'payouts' => OwnerPayout::query()
                ->where('owner_id', $owner->id)
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'ledger' => OwnerLedgerEntry::query()
                ->where('owner_id', $owner->id)
                ->latest()
                ->paginate(20, ['*'], 'ledger_page')
                ->withQueryString(),
            'payoutProfile' => [
                'payout_method' => $owner->payout_method,
                'payout_account_holder' => $owner->payout_account_holder,
                'payout_account_identifier' => $owner->payout_account_identifier,
                'payout_note' => $owner->payout_note,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'owner_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->payouts->request($request->user(), (string) $data['amount'], $data['owner_note'] ?? null);

        return back()->with('success', 'Payout request submitted.');
    }

    public function statement(Request $request, OwnerPayout $payout)
    {
        $this->authorizePayout($request, $payout);

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
            'payout' => $payout->load('owner:id,name,email'),
            'entries' => $entries,
        ]);
    }

    public function exportCsv(Request $request, OwnerPayout $payout): StreamedResponse
    {
        $this->authorizePayout($request, $payout);

        return PayoutStatementCsv::download($payout);
    }

    private function authorizePayout(Request $request, OwnerPayout $payout): void
    {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || $payout->owner_id === $user->id), 403);
    }
}
