<?php

namespace App\Http\Controllers\User;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $userId = $request->user()->id;

        $base = Booking::query()->where('user_id', $userId);

        $upcoming = (clone $base)
            ->with('workspace:id,name,location,image_url')
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->where('start_at', '>=', now())
            ->orderBy('start_at')
            ->limit(5)
            ->get();

        return Inertia::render('User/Dashboard', [
            'stats' => [
                'bookings_count' => (clone $base)->count(),
                'pending_count' => (clone $base)->where('status', BookingStatus::Pending)->count(),
                'confirmed_count' => (clone $base)->where('status', BookingStatus::Confirmed)->count(),
                'unpaid_count' => (clone $base)->where('payment_status', PaymentStatus::Unpaid)->count(),
            ],
            'upcoming' => $upcoming,
        ]);
    }
}
