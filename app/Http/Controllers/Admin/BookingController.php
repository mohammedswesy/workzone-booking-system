<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BookingController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Booking::class, 'booking');
    }

    public function index()
    {
        $bookings = Booking::with('workspace:id,name','user:id,name')->latest()->paginate(30);
        return Inertia::render('Admin/Bookings/Index', compact('bookings'));
    }

    public function edit(Booking $booking)
    {
        return Inertia::render('Admin/Bookings/edit', compact('booking'));
    }

    public function update(Request $request, Booking $booking)
    {
    $data = $request->validate([
        'status' => ['required', Rule::in(Booking::STATUSES)],
    ]);

        $booking->update($data);
        return back()->with('success','تم التحديث.');
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();
        return back()->with('success','تم الحذف.');
    }
}
