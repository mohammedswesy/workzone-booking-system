<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayout;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ledger\OwnerLedgerService;
use App\Support\Csv\CsvExporter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerAccountController extends Controller
{
    public function __construct(
        private readonly OwnerLedgerService $ledger,
    ) {}

    public function index(Request $request)
    {
        $search = trim($request->string('search')->toString());

        $owners = User::query()
            ->where('role', Role::Owner)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $accounts = $owners->getCollection()->map(function (User $owner) {
            $snapshot = $this->ledger->accountSnapshot($owner);
            $open = $this->ledger->openPayoutFor($owner);

            return [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'is_active' => $owner->is_active,
                'earnings' => $snapshot['earnings'],
                'commission_taken' => $snapshot['commission_taken'],
                'refunds' => $snapshot['refunds'],
                'paid_out' => $snapshot['paid_out'],
                'available' => $snapshot['available'],
                'pending' => $snapshot['pending'],
                'balance' => $snapshot['balance'],
                'open_payout' => $open ? [
                    'id' => $open->id,
                    'amount' => $open->amount_approved ?? $open->amount_requested,
                    'status' => $open->status?->value ?? $open->status,
                ] : null,
            ];
        });

        $owners->setCollection($accounts);

        return Inertia::render('Admin/OwnerAccounts/Index', [
            'owners' => $owners,
            'filters' => ['search' => $search],
        ]);
    }

    public function show(Request $request, User $owner)
    {
        abort_unless($owner->isOwner(), 404);

        $filters = $this->detailFilters($request);
        $snapshot = $this->ledger->accountSnapshot($owner, $filters['from'], $filters['to']);
        $open = $this->ledger->openPayoutFor($owner);

        $ledgerQuery = OwnerLedgerEntry::query()
            ->where('owner_id', $owner->id)
            ->where('status', 'posted')
            ->when($filters['from'], fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'], fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->when($filters['workspace_id'], function ($q) use ($filters) {
                $q->whereHas('booking', fn ($b) => $b->where('workspace_id', $filters['workspace_id']));
            })
            ->with(['booking.workspace:id,name'])
            ->latest()
            ->latest('id');

        return Inertia::render('Admin/OwnerAccounts/Show', [
            'owner' => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
            ],
            'snapshot' => $snapshot,
            'openPayout' => $open,
            'workspaceBreakdown' => $this->ledger->workspaceBreakdown($owner, $filters['from'], $filters['to']),
            'ledger' => $ledgerQuery->paginate(25)->withQueryString(),
            'payouts' => OwnerPayout::query()
                ->where('owner_id', $owner->id)
                ->latest()
                ->paginate(15, ['*'], 'payouts_page')
                ->withQueryString(),
            'workspaces' => Workspace::query()
                ->where('owner_id', $owner->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function summary(Request $request, User $owner)
    {
        abort_unless($owner->isOwner(), 404);

        $filters = $this->detailFilters($request);
        $snapshot = $this->ledger->accountSnapshot($owner, $filters['from'], $filters['to']);

        return Inertia::render('Admin/OwnerAccounts/Summary', [
            'owner' => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
            ],
            'snapshot' => $snapshot,
            'workspaceBreakdown' => $this->ledger->workspaceBreakdown($owner, $filters['from'], $filters['to']),
            'filters' => $filters,
            'generatedAt' => now()->toIso8601String(),
        ]);
    }

    public function exportCsv(Request $request, User $owner): StreamedResponse
    {
        abort_unless($owner->isOwner(), 404);
        CsvExporter::applyLocale($request);

        $filters = $this->detailFilters($request);

        $headers = [
            CsvExporter::label('created_at'),
            CsvExporter::label('entry_type'),
            CsvExporter::label('amount'),
            CsvExporter::label('currency'),
            CsvExporter::label('booking_id'),
            CsvExporter::label('workspace'),
            CsvExporter::label('note'),
            CsvExporter::label('payout_id'),
        ];

        $filename = 'owner-'.$owner->id.'-account.csv';

        return CsvExporter::download($filename, $headers, function (callable $write) use ($owner, $filters) {
            OwnerLedgerEntry::query()
                ->where('owner_id', $owner->id)
                ->where('status', 'posted')
                ->when($filters['from'], fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
                ->when($filters['to'], fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
                ->when($filters['workspace_id'], function ($q) use ($filters) {
                    $q->whereHas('booking', fn ($b) => $b->where('workspace_id', $filters['workspace_id']));
                })
                ->with(['booking.workspace:id,name'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->cursor()
                ->each(function (OwnerLedgerEntry $entry) use ($write) {
                    $write([
                        CsvExporter::formatDateTime($entry->created_at),
                        CsvExporter::statusLabel('ledger', $entry->type),
                        CsvExporter::formatMoney($entry->amount),
                        $entry->currency,
                        $entry->booking_id,
                        $entry->booking?->workspace?->name,
                        $entry->note,
                        $entry->payout_id,
                    ]);
                });
        });
    }

    /**
     * @return array{from: ?string, to: ?string, workspace_id: ?int}
     */
    private function detailFilters(Request $request): array
    {
        return [
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
            'workspace_id' => $request->integer('workspace_id') ?: null,
        ];
    }
}
