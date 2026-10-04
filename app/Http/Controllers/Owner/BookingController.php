<?php

namespace App\Http\Controllers\Owner;

use App\Enums\BookingStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBookingStatusRequest;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\MarkBookingPaid;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $owner = $request->user();
        $status = (string) $request->input('status', '');
        $search = (string) $request->input('search', '');
        $perPage = (int) $request->input('per_page', 12);

        $this->authorize('viewAny', Booking::class);

        $query = Booking::query()
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
            'filters' => [
                'status' => $status,
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

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

    public function edit(Request $request, Booking $booking)
    {
        $this->authorize('update', $booking);

        $booking->loadMissing([
            'workspace:id,name',
            'user:id,name,email',
        ]);

        return Inertia::render('Owner/Bookings/Edit', [
            'booking' => $booking,
            'statuses' => [
                BookingStatus::Confirmed->value,
                BookingStatus::Cancelled->value,
            ],
        ]);
    }

    public function update(UpdateBookingStatusRequest $request, Booking $booking, MarkBookingPaid $markPaid)
    {
        $status = BookingStatus::from($request->validated('status'));

        if ($status === BookingStatus::Confirmed) {
            $payment = $booking->payments()
                ->where('provider', PaymentProvider::Manual)
                ->latest()
                ->first();

            if (! $payment) {
                $payment = Payment::create([
                    'booking_id' => $booking->id,
                    'provider' => PaymentProvider::Manual,
                    'reference' => 'manual-confirm-'.$booking->id.'-'.uniqid(),
                    'amount' => $booking->total_price,
                    'currency' => config('payments.currency', 'USD'),
                    'status' => PaymentStatus::Pending,
                    'metadata' => ['source' => 'owner_manual_confirmation'],
                ]);
            }

            if ($payment->status !== PaymentStatus::Paid) {
                $markPaid->handle($payment, [
                    'confirmed_by' => $request->user()->id,
                    'source' => 'owner_manual_confirmation',
                ]);
            }

            return back()->with('success', 'تم تأكيد الحجز بعد التحقق اليدوي من الدفع.');
        }

        $booking->update(['status' => $status]);

        return back()->with('success', 'تم تحديث حالة الحجز.');
    }

    public function destroy(Request $request, Booking $booking)
    {
        $this->authorize('delete', $booking);

        $booking->delete();

        return redirect()->route('owner.bookings.index')
            ->with('success', 'تم حذف الحجز.');
    }
}
