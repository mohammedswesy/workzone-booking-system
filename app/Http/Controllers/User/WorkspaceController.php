<?php

namespace App\Http\Controllers\User;

use App\Enums\BookingStatus;
use App\Filters\WorkspaceFilter;
use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    public function index(Request $request, WorkspaceFilter $filter)
    {
        $query = Workspace::query()
            ->with([
                'activeOffers',
                'place:id,name,city',
                'amenities:id,name,slug',
                'images' => fn ($q) => $q->orderBy('sort_order')->limit(1),
            ]);

        $filter->apply($query);

        $spaces = $query
            ->latest()
            ->paginate($filter->values()['per_page'])
            ->withQueryString();

        return Inertia::render('User/Workspaces/Index', [
            'spaces' => $spaces,
            'filters' => $filter->values(),
            'locations' => Location::query()->orderBy('name')->get(['id', 'name', 'city']),
            'amenities' => Amenity::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function show(Workspace $workspace)
    {
        $user = Auth::user();

        $workspace->loadMissing('owner:id,name,is_active');

        abort_unless(
            $workspace->status === \App\Enums\WorkspaceStatus::Published
            && $workspace->owner
            && $workspace->owner->is_active,
            404
        );

        $workspace->load([
            'activeOffers',
            'place',
            'amenities:id,name,slug,icon',
            'images' => fn ($q) => $q->orderBy('sort_order'),
            'owner:id,name',
        ]);

        $pendingBookingId = null;
        if ($user) {
            $pendingBookingId = Booking::where('user_id', $user->id)
                ->where('workspace_id', $workspace->id)
                ->where('status', BookingStatus::Pending)
                ->value('id');
        }

        return Inertia::render('User/Workspaces/Show', [
            'workspace' => $workspace,
            'can_book' => (bool) $user,
            'pending_booking_id' => $pendingBookingId,
        ]);
    }
}
