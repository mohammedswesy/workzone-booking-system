<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ForcedPasswordChangeController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Auth/ForceChangePassword');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('auth.password'),
            ]);
        }

        if (Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Your new password must be different from your current password.',
            ]);
        }

        $user->forceFill([
            'password' => $request->input('password'),
            'must_change_password' => false,
        ])->save();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Password updated.');
    }
}
