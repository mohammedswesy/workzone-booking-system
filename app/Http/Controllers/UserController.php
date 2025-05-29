<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    // عرض قائمة المستخدمين
    public function index()
    {
        $users = User::all();
        return view('admin.users.index', compact('users'));
    }

    // عرض نموذج إنشاء مستخدم جديد
    public function create()
    {
        return view('admin.users.create');
    }

    // حفظ المستخدم الجديد
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'role' => 'required|in:admin,owner,user',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'],
        ]);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    // عرض صفحة تعديل مستخدم
    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    // تحديث بيانات المستخدم
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:admin,owner,user',
        ]);

        $user->update($validated);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    // حذف مستخدم
    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
