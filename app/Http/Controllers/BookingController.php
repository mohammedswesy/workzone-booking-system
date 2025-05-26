<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function index()
    {
        $bookings = Booking::where('user_id', Auth::id())->with('workspace')->get();
        return view('bookings.index', compact('bookings'));
    }

    public function create()
    {
        $workspaces = Workspace::all();
        return view('bookings.create', compact('workspaces'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'workspace_id' => 'required|exists:workspaces,id',
            'hours' => 'required|integer|min:1',
        ]);

        $workspace = Workspace::findOrFail($request->workspace_id);
        $total_price = $workspace->price_per_hour * $request->hours;

        $existingBooking = Booking::where('user_id', Auth::id())
            ->where('workspace_id', $request->workspace_id)
            ->where('status', 'pending')
            ->first();

        if ($existingBooking) {
            return redirect()->back()->withErrors(['workspace_id' => 'لديك حجز معلق بنفس مكان العمل.'])->withInput();
        }

        Booking::create([
            'user_id' => Auth::id(),
            'workspace_id' => $request->workspace_id,
            'hours' => $request->hours,
            'total_price' => $total_price,
            'status' => 'pending',
        ]);

        return redirect()->route('bookings.index')->with('success', 'تم إنشاء الحجز بنجاح.');
    }

    public function edit(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            abort(403);
        }

        $workspaces = Workspace::all();
        return view('bookings.edit', compact('booking', 'workspaces'));
    }

    public function update(Request $request, Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'workspace_id' => 'required|exists:workspaces,id',
            'hours' => 'required|integer|min:1',
        ]);

        $workspace = Workspace::findOrFail($request->workspace_id);
        $total_price = $workspace->price_per_hour * $request->hours;

        $booking->update([
            'workspace_id' => $request->workspace_id,
            'hours' => $request->hours,
            'total_price' => $total_price,
        ]);

        return redirect()->route('bookings.index')->with('success', 'تم تحديث الحجز بنجاح.');
    }

    public function destroy(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            abort(403);
        }

        $booking->delete();
        return redirect()->route('bookings.index')->with('success', 'تم حذف الحجز بنجاح.');
    }
}
