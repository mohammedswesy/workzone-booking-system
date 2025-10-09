<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    // يسمح بعرض القائمة للجميع (مستخدمين مسجلين)
    public function viewAny(?User $user): bool
    {
        return true;
    }

    // عرض عنصر محدد
    public function view(?User $user, Workspace $workspace): bool
    {
        return true;
    }

    // إنشاء مساحة: فقط admin أو owner
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin','owner']);
    }

    // تعديل: admin أو مالك المساحة
    public function update(User $user, Workspace $workspace): bool
    {
        return $user->role === 'admin' || $workspace->owner_id === $user->id;
    }

    // حذف: admin أو مالك المساحة
    public function delete(User $user, Workspace $workspace): bool
    {
        return $user->role === 'admin' || $workspace->owner_id === $user->id;
    }

    // اختياري
    public function restore(User $user, Workspace $workspace): bool
    {
        return $user->role === 'admin';
    }

    public function forceDelete(User $user, Workspace $workspace): bool
    {
        return $user->role === 'admin';
    }
}
