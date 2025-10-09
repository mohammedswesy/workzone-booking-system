<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    // ممكن تضيف authorizeResource لو كتبت UserPolicy
    public function index()
    {
        $users = User::select('id','name','email','role','created_at')->latest()->paginate(20);
        return Inertia::render('Admin/Users/Index', compact('users'));
    }

    public function edit(User $user)
    {
        return Inertia::render('Admin/Users/Index', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required','in:user,owner,admin'],
        ]);
        $user->update($data);
        return back()->with('success','تم تحديث الدور.');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return back()->with('success','تم حذف المستخدم.');
    }
}
