<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;

class TwoFactorController extends Controller
{
    public function setup(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        if (! $user->two_factor_secret) {
            $secret = Totp::generateSecret();
            $codes = Totp::recoveryCodes();
            $user->forceFill([
                'two_factor_secret' => Crypt::encryptString($secret),
                'two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes)),
                'two_factor_confirmed_at' => null,
            ])->save();
        } else {
            $secret = Crypt::decryptString($user->two_factor_secret);
            $codes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true) ?: [];
        }

        return Inertia::render('Admin/TwoFactor/Setup', [
            'secret' => $secret,
            'otpauthUrl' => Totp::otpAuthUrl($secret, $user->email),
            'recoveryCodes' => $codes,
            'confirmed' => (bool) $user->two_factor_confirmed_at,
        ]);
    }

    public function confirm(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $secret = Crypt::decryptString($user->two_factor_secret);
        abort_unless(Totp::verify($secret, $data['code']), 422, 'Invalid authenticator code.');

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $request->session()->put('auth.two_factor_passed', true);

        return redirect()->route('admin.dashboard')->with('success', 'Two-factor authentication enabled.');
    }

    public function challenge(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return Inertia::render('Admin/TwoFactor/Challenge');
    }

    public function verify(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $secret = Crypt::decryptString($user->two_factor_secret);
        $ok = Totp::verify($secret, $data['code']);

        if (! $ok) {
            $codes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true) ?: [];
            $match = array_search(strtoupper(trim($data['code'])), array_map('strtoupper', $codes), true);
            if ($match !== false) {
                unset($codes[$match]);
                $user->forceFill([
                    'two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($codes))),
                ])->save();
                $ok = true;
            }
        }

        abort_unless($ok, 422, 'Invalid two-factor code.');

        $request->session()->put('auth.two_factor_passed', true);

        return redirect()->intended(route('admin.dashboard'));
    }
}
