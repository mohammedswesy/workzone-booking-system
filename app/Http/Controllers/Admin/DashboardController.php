<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $revenue = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->sum('amount');

        $bookingsByStatus = Booking::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentBookings = Booking::query()
            ->with(['workspace:id,name', 'user:id,name'])
            ->latest()
            ->limit(8)
            ->get(['id', 'user_id', 'workspace_id', 'total_price', 'status', 'payment_status', 'created_at']);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::where('role', Role::User)->count(),
                'owners' => User::where('role', Role::Owner)->count(),
                'workspaces' => Workspace::count(),
                'bookings' => Booking::count(),
                'pending_bookings' => Booking::where('status', BookingStatus::Pending)->count(),
                'revenue' => number_format((float) $revenue, 2, '.', ''),
            ],
            'bookingsByStatus' => $bookingsByStatus,
            'recentBookings' => $recentBookings,
        ]);
    }
}
