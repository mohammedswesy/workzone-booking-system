<?php

namespace App\Http\Controllers\Owner;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $ownerId = $request->user()->id;

        $workspaceIds = Workspace::query()
            ->where('owner_id', $ownerId)
            ->pluck('id');

        $bookingsQuery = Booking::query()->whereIn('workspace_id', $workspaceIds);

        $revenue = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereHas('booking', fn ($q) => $q->whereIn('workspace_id', $workspaceIds))
            ->sum('amount');

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

        return Inertia::render('Owner/Dashboard', [
            'stats' => [
                'workspaces_count' => $workspaceIds->count(),
                'bookings_count' => (clone $bookingsQuery)->count(),
                'pending_count' => (clone $bookingsQuery)->where('status', BookingStatus::Pending)->count(),
                'active_offers_count' => Offer::query()
                    ->where('owner_id', $ownerId)
                    ->active()
                    ->count(),
                'revenue' => number_format((float) $revenue, 2, '.', ''),
            ],
            'topWorkspaces' => $topWorkspaces,
        ]);
    }
}
