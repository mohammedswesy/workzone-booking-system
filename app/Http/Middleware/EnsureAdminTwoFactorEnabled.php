<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user?->isAdmin()) {
            return $next($request);
        }

        if ($request->routeIs('admin.two-factor.*') || $request->routeIs('logout')) {
            return $next($request);
        }

        $enforce = (bool) config('app.admin_2fa_enforced', false);
        if (! $enforce) {
            return $next($request);
        }

        if (! $user->two_factor_confirmed_at) {
            return redirect()->route('admin.two-factor.setup');
        }

        if (! $request->session()->get('auth.two_factor_passed')) {
            return redirect()->route('admin.two-factor.challenge');
        }

        return $next($request);
    }
}
