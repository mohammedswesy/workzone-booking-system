<?php

namespace App\Http\Middleware;

use App\Support\PaymentsConfig;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $role = $user?->role;

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'role' => $role instanceof \BackedEnum ? $role->value : $role,
            ],
            'brand' => [
                'name' => 'WorkZone',
                'tagline' => 'Gaza Tashreel',
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'payments' => [
                'paypalEnabled' => PaymentsConfig::paypalAvailable(),
                'manualEnabled' => (bool) config('payments.providers.manual.enabled', true),
            ],
        ]);
    }
}
