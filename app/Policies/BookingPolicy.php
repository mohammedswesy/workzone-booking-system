<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Booking;

class BookingPolicy
{
    // يشوف حجوزاته أو حجوزات مساحاته (إن كان owner) أو الكل إن كان admin
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['user','owner','admin']);
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->role === 'admin') return true;
        if ($booking->user_id === $user->id) return true; // صاحب الحجز
        // صاحب المساحة المالكة للحجز
        return optional($booking->workspace)->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['user','owner','admin']);
    }

    public function update(User $user, Booking $booking): bool
    {
        if ($user->role === 'admin') return true;
        if ($booking->user_id === $user->id) return true;
        return optional($booking->workspace)->owner_id === $user->id;
    }

    public function delete(User $user, Booking $booking): bool
    {
        if ($user->role === 'admin') return true;
        if ($booking->user_id === $user->id) return true;
        return optional($booking->workspace)->owner_id === $user->id;
    }
}
