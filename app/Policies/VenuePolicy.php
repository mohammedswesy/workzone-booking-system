<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Venue $venue): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [Role::Admin, Role::Owner], true);
    }

    public function update(User $user, Venue $venue): bool
    {
        return $user->role === Role::Admin || $venue->owner_id === $user->id;
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $user->role === Role::Admin || $venue->owner_id === $user->id;
    }

    public function restore(User $user, Venue $venue): bool
    {
        return $user->role === Role::Admin;
    }

    public function forceDelete(User $user, Venue $venue): bool
    {
        return $user->role === Role::Admin;
    }
}
