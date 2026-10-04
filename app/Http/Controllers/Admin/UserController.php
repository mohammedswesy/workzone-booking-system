<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::select('id', 'name', 'email', 'role', 'created_at')->latest()->paginate(20);

        return Inertia::render('Admin/Users/Index', compact('users'));
    }

    public function edit(User $user)
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user->only('id', 'name', 'email', 'role'),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        // role is intentionally not mass-assignable.
        $user->forceFill(['role' => $data['role']])->save();

        return back()->with('success', 'تم تحديث الدور.');
    }

    public function destroy(User $user)
    {
        $user->delete();

        return back()->with('success', 'تم حذف المستخدم.');
    }
}
