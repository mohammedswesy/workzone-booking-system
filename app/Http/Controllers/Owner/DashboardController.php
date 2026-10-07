<?php

namespace App\Http\Controllers\Owner;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Venue;
use App\Models\Workspace;
use App\Services\Ledger\OwnerLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private readonly OwnerLedgerService $ledger,
    ) {}

    public function __invoke(Request $request)
    {
        $owner = $request->user();
        $ownerId = $owner->id;
        $balances = $this->ledger->balancesFor($owner);

        $workspaceIds = Workspace::query()
            ->where('owner_id', $ownerId)
            ->pluck('id');

        $bookingsQuery = Booking::query()->whereIn('workspace_id', $workspaceIds);

        $topWorkspaces = Booking::query()
            ->select('workspace_id', DB::raw('COUNT(*) as bookings_count'), DB::raw('SUM(total_price) as revenue'))
            ->whereIn('workspace_id', $workspaceIds)
            ->groupBy('workspace_id')
            ->orderByDesc('bookings_count')
            ->with('workspace:id,name')
            ->take(5)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->workspace_id,
                'name' => $row->workspace?->name ?? '—',
                'bookings_count' => (int) $row->bookings_count,
                'revenue' => (string) $row->revenue,
            ]);

        $recentBookings = (clone $bookingsQuery)
            ->with([
                'workspace:id,name',
                'user:id,name,email',
            ])
            ->latest()
            ->limit(5)
            ->get();

        $activeOffers = Offer::query()
            ->where('owner_id', $ownerId)
            ->active()
            ->with('workspace:id,name')
            ->latest()
            ->limit(5)
            ->get(['id', 'workspace_id', 'title', 'discount_percent', 'starts_at', 'ends_at', 'is_active']);

        $missingLocationVenues = Venue::query()
            ->where('owner_id', $ownerId)
            ->withoutCoordinates()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'status']);

        return Inertia::render('Owner/Dashboard', [
            'stats' => [
                'workspaces_count' => $workspaceIds->count(),
                'bookings_count' => (clone $bookingsQuery)->count(),
                'pending_count' => (clone $bookingsQuery)->where('status', BookingStatus::Pending)->count(),
                'active_offers_count' => Offer::query()
                    ->where('owner_id', $ownerId)
                    ->active()
                    ->count(),
                'available_balance' => $balances['available'],
                'pending_balance' => $balances['pending'],
                'ledger_balance' => $balances['balance'],
                'venues_without_coordinates' => $missingLocationVenues->count(),
            ],
            'balances' => $balances,
            'topWorkspaces' => $topWorkspaces,
            'recentBookings' => $recentBookings,
            'activeOffers' => $activeOffers,
            'needsPaymentSetup' => [],
            'missingLocationVenues' => $missingLocationVenues,
        ]);
    }
}
