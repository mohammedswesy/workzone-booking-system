<?php

namespace App\Http\Middleware;


use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware

{
    /**
     * Usage:
     *  ->middleware('role:admin')
     *  ->middleware('role:admin,owner')          // فاصلة
     *  ->middleware('role:owner','user')         // باراميترات متعددة
     */
    public function handle(Request $request, Closure $next, ...$args): Response
    {
        $user = $request->user();

        // غير مسجل دخول → رجّعه على صفحة اللوجين (بدل 401)
        if (! $user) {
            // غيّر 'login' إذا عندك اسم روت مختلف
            return redirect()->route('login');
        }

        // لو عندك method isAdmin() نسمح دائمًا
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }

        // جمع الأدوار المسموح بها من الباراميترات + تفكيك الفواصل
        $allowed = collect($args)
            ->flatMap(fn ($a) => explode(',', (string) $a))
            ->map(fn ($r) => strtolower(trim($r)))
            ->filter()
            ->unique()
            ->values();

        $role = strtolower((string) $user->role);

        if (! $allowed->contains($role)) {
            // لا يملك صلاحية
            abort(403, 'ليس لديك صلاحية للوصول إلى هذه الصفحة.');
        }

        return $next($request);
    }
}
