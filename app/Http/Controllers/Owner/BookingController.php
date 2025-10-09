<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BookingController extends Controller
{
    /**
     * قائمة حجوزات مساحات المالك مع فلاتر وباجينيشن.
     * route: owner.bookings.index
     */
    public function index(Request $request)
    {
        $owner   = $request->user();
        $status  = (string) $request->input('status', '');
        $search  = (string) $request->input('search', '');
        $perPage = (int) $request->input('per_page', 12);

        $this->authorize('viewAny', Booking::class);

        $query = Booking::query()
            // حجوزات المساحات التي يملكها هذا المالك
            ->whereHas('workspace', fn ($w) => $w->where('owner_id', $owner->id))
            ->with([
                'workspace:id,name',
                'user:id,name,email',
            ])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($x) use ($search) {
                    $x->whereHas('workspace', fn ($w) => $w->where('name', 'like', "%{$search}%"))
                      ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                                                       ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest();

        $bookings = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Owner/Bookings/Index', [
            'bookings' => $bookings,
            'filters'  => [
                'status'   => $status,
                'search'   => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * عرض تفاصيل حجز.
     * route: owner.bookings.show
     */
    public function show(Request $request, Booking $booking)
    {
        $this->authorize('view', $booking);

        $booking->loadMissing([
            'workspace:id,name,location,owner_id',
            'user:id,name,email',
        ]);

        return Inertia::render('Owner/Bookings/Show', [
            'booking' => $booking,
        ]);
    }

    /**
     * شاشة تعديل (اختياري – لو بدك تستخدمها).
     * route: owner.bookings.edit
     */
    public function edit(Request $request, Booking $booking)
    {
        $this->authorize('update', $booking);

        $booking->loadMissing([
            'workspace:id,name',
            'user:id,name,email',
        ]);

        return Inertia::render('Owner/Bookings/Edit', [
            'booking' => $booking,
            'statuses' => Booking::STATUSES, // ['pending','paid','cancelled']
        ]);
    }

    /**
     * تحديث حالة الحجز (paid / cancelled … الخ).
     * يقبل: status ضمن Booking::STATUSES
     * route: owner.bookings.update (PUT/PATCH)
     */
    public function update(Request $request, Booking $booking)
    {
        $this->authorize('update', $booking);

        $data = $request->validate([
            'status' => ['required', Rule::in(Booking::STATUSES)],
        ]);

        $booking->update(['status' => $data['status']]);

        return back()->with('success', 'تم تحديث حالة الحجز.');
    }

    /**
     * حذف حجز (اختياري).
     * route: owner.bookings.destroy
     */
    public function destroy(Request $request, Booking $booking)
    {
        $this->authorize('delete', $booking);

        $booking->delete();

        return redirect()->route('owner.bookings.index')
            ->with('success', 'تم حذف الحجز.');
    }
}
