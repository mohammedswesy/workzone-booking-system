<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->input('search');
        $perPage = (int) ($request->input('per_page') ?? 12);

        $spaces = Workspace::query()
            ->when($q, fn($query) =>
                $query->where(fn($w) =>
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('location', 'like', "%{$q}%")
                )
            )
            ->with('activeOffers') // مهم لعدم تكرار الاستعلامات
            ->select('id','name','location','capacity','price_per_hour','image_url','owner_id')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('User/Workspaces/Index', [
            'spaces'  => $spaces, // يحتوي على appends تلقائيًا
            'filters' => ['search' => $q, 'per_page' => $perPage],
        ]);
    }

    public function show(Workspace $workspace)
    {
        $user = Auth::user();

        $workspace->load('activeOffers');

        $pendingBookingId = null;
        if ($user) {
            $pendingBookingId = Booking::where('user_id', $user->id)
                ->where('workspace_id', $workspace->id)
                ->where('status', Booking::STATUS_PENDING ?? 'pending')
                ->value('id');
        }

        // خذ الحقول الأساسية… والقيم المحسوبة ستأتي تلقائيًا بفضل $appends
        $data = $workspace->only([
            'id','name','location','capacity','price_per_hour','image_url','created_at','owner_id'
        ]);
        // (اختياري) ضامن لو حابب تتأكد
        $data['active_discount_percent']  = $workspace->active_discount_percent;
        $data['effective_price_per_hour'] = $workspace->effective_price_per_hour;
        $data['offer_label']              = $workspace->offer_label;

        return Inertia::render('User/Workspaces/Show', [
            'workspace'          => $data,
            'can_book'           => (bool) $user,
            'pending_booking_id' => $pendingBookingId,
        ]);
    }
}
