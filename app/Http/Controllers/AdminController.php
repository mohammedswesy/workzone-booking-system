<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Booking;

class AdminController extends Controller
{
    //
//     public function index()
// {
//     return view('admin.index'); // قم بإنشاء ملف العرض لاحقاً
// }

public function index()
    {
        $usersCount = User::count();
        $bookingsCount = Booking::count();
        $revenue = Booking::sum('amount'); // افترض وجود حقل "amount" للحجوزات

        return view('admin.dashboard', compact('usersCount', 'bookingsCount', 'revenue'));
    }

    public function manageUsers()
    {
        $users = User::all();
        return view('admin.users', compact('users'));
    }

    public function manageBookings()
    {
        $bookings = Booking::all();
        return view('admin.bookings', compact('bookings'));
    }
}
