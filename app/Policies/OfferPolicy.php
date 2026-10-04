<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Offer;
use App\Models\User;

class OfferPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::Owner, Role::Admin], true);
    }

    public function view(User $user, Offer $offer): bool
    {
        return $user->role === Role::Admin || $offer->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [Role::Owner, Role::Admin], true);
    }

    public function update(User $user, Offer $offer): bool
    {
        return $user->role === Role::Admin || $offer->owner_id === $user->id;
    }

    public function delete(User $user, Offer $offer): bool
    {
        return $user->role === Role::Admin || $offer->owner_id === $user->id;
    }
}
