<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param mixed ...$roles
     * @return Response
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        dd('Middleware role is running');
        if (!Auth::check()) {
            abort(403, 'Unauthorized action.');
        }

        if (in_array(Auth::user()->role, $roles)) {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
    }
}
