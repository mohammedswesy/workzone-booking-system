<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminSessionIsFresh
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user?->isAdmin()) {
            return $next($request);
        }

        $idleMinutes = max(5, (int) config('app.admin_idle_minutes', 30));
        $lastSeen = $user->last_seen_at;
        if ($lastSeen && $lastSeen->lt(now()->subMinutes($idleMinutes))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Admin session expired due to inactivity. Please sign in again.');
        }

        $user->forceFill(['last_seen_at' => now()])->save();

        return $next($request);
    }
}
