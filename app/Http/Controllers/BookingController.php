<?php

namespace App\Http\Controllers;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;

class BookingController extends Controller
{
    //
     public function index()
    {
        // جلب الحجوزات الخاصة بالمستخدم الحالي
        $bookings = Booking::where('user_id', Auth::id())->with('workspace')->get();

        return view('bookings.index', compact('bookings'));
    }
}
