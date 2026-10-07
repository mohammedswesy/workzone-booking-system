<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBookingStatusRequest;
use App\Models\Booking;
use App\Services\Ledger\OwnerLedgerService;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BookingController extends Controller
{
    public function __construct(
        private readonly OwnerLedgerService $ledger,
    ) {
        $this->authorizeResource(Booking::class, 'booking');
    }

    public function index()
    {
        $bookings = Booking::with('workspace:id,name', 'user:id,name')->latest()->paginate(30);

        return Inertia::render('Admin/Bookings/Index', compact('bookings'));
    }

    public function show(Booking $booking)
    {
        $booking->load([
            'workspace:id,name,owner_id',
            'workspace.owner:id,name,email',
            'user:id,name,email',
            'payments' => fn ($q) => $q->latest(),
        ]);

        return Inertia::render('Admin/Bookings/Show', [
            'booking' => $booking,
        ]);
    }

    public function edit(Booking $booking)
    {
        return $this->show($booking);
    }

    public function update(UpdateBookingStatusRequest $request, Booking $booking)
    {
        $status = BookingStatus::from($request->validated('status'));
        $previous = $booking->status;

        if ($status === BookingStatus::Confirmed && $booking->payment_status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'status' => 'Booking can only be confirmed after payment is paid.',
            ]);
        }

        $booking->update(['status' => $status]);

        if ($status === BookingStatus::Completed) {
            $this->ledger->postCompletionEntries($booking->fresh());
        }

        if ($status === BookingStatus::Cancelled && $previous !== BookingStatus::Cancelled) {
            $this->ledger->postRefundReversal($booking->fresh());
        }

        return back()->with('success', 'Booking updated.');
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        return back()->with('success', 'Booking deleted.');
    }
}
