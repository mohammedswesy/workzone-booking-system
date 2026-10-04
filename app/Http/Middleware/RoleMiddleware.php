<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Usage:
     *  ->middleware('role:admin')
     *  ->middleware('role:admin,owner')
     */
    public function handle(Request $request, Closure $next, ...$args): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        $allowed = collect($args)
            ->flatMap(fn ($a) => explode(',', (string) $a))
            ->map(fn ($r) => strtolower(trim($r)))
            ->filter()
            ->unique()
            ->values();

        $role = $user->role instanceof Role
            ? $user->role->value
            : strtolower((string) $user->role);

        if (! $allowed->contains($role)) {
            abort(403, 'ليس لديك صلاحية للوصول إلى هذه الصفحة.');
        }

        return $next($request);
    }
}
