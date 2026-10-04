<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::User, Role::Owner, Role::Admin], true);
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if ($booking->user_id === $user->id) {
            return true;
        }

        return $booking->workspace?->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::User;
    }

    public function update(User $user, Booking $booking): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if ($booking->user_id === $user->id) {
            return true;
        }

        // Owners may update bookings on their workspaces (status only enforced in controller / Phase 2).
        return $booking->workspace?->owner_id === $user->id;
    }

    public function delete(User $user, Booking $booking): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if ($booking->user_id === $user->id) {
            return true;
        }

        return $booking->workspace?->owner_id === $user->id;
    }
}
