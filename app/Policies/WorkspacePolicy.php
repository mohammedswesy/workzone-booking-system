<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Workspace $workspace): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [Role::Admin, Role::Owner], true);
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $user->role === Role::Admin || $workspace->owner_id === $user->id;
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $user->role === Role::Admin || $workspace->owner_id === $user->id;
    }

    public function restore(User $user, Workspace $workspace): bool
    {
        return $user->role === Role::Admin;
    }

    public function forceDelete(User $user, Workspace $workspace): bool
    {
        return $user->role === Role::Admin;
    }
}
