<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBookingStatusRequest;
use App\Models\Booking;
use Inertia\Inertia;

class BookingController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Booking::class, 'booking');
    }

    public function index()
    {
        $bookings = Booking::with('workspace:id,name', 'user:id,name')->latest()->paginate(30);

        return Inertia::render('Admin/Bookings/Index', compact('bookings'));
    }

    public function show(Booking $booking)
    {
        $booking->load(['workspace:id,name', 'user:id,name,email']);

        return Inertia::render('Admin/Bookings/Show', compact('booking'));
    }

    public function edit(Booking $booking)
    {
        return $this->show($booking);
    }

    public function update(UpdateBookingStatusRequest $request, Booking $booking)
    {
        $booking->update([
            'status' => $request->validated('status'),
        ]);

        return back()->with('success', 'تم التحديث.');
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        return back()->with('success', 'تم الحذف.');
    }
}
