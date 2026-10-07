<?php

namespace App\Http\Controllers\Owner;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBookingStatusRequest;
use App\Models\Booking;
use App\Services\Ledger\OwnerLedgerService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BookingController extends Controller
{
    public function __construct(
        private readonly OwnerLedgerService $ledger,
    ) {}

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
            'payments' => fn ($q) => $q->latest(),
        ]);

        // Owners see payment status only — never proof path/URL or transfer refs.
        $payments = $booking->payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'provider' => $payment->provider?->value ?? $payment->provider,
                'status' => $payment->status?->value ?? $payment->status,
                'amount' => $payment->amount,
                'created_at' => $payment->created_at,
                'paid_at' => $payment->paid_at,
            ];
        })->values();

        $booking->unsetRelation('payments');

        return Inertia::render('Owner/Bookings/Show', [
            'booking' => [
                ...$booking->toArray(),
                'payments' => $payments,
            ],
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
                BookingStatus::Completed->value,
                BookingStatus::Cancelled->value,
                BookingStatus::NoShow->value,
            ],
        ]);
    }

    public function update(UpdateBookingStatusRequest $request, Booking $booking)
    {
        $status = BookingStatus::from($request->validated('status'));
        $previous = $booking->status;

        if ($status === BookingStatus::Confirmed && $booking->payment_status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'status' => 'Booking can only be confirmed after the platform payment is paid.',
            ]);
        }

        $booking->update(['status' => $status]);

        if ($status === BookingStatus::Completed) {
            $this->ledger->postCompletionEntries($booking->fresh());
        }

        if ($status === BookingStatus::Cancelled && $previous !== BookingStatus::Cancelled) {
            $this->ledger->postRefundReversal($booking->fresh());
        }

        return back()->with('success', 'Booking status updated.');
    }

    public function destroy(Request $request, Booking $booking)
    {
        $this->authorize('delete', $booking);

        $booking->update([
            'status' => BookingStatus::Cancelled,
        ]);
        $this->ledger->postRefundReversal($booking->fresh());

        return redirect()->route('owner.bookings.index')
            ->with('success', 'Booking cancelled.');
    }
}
