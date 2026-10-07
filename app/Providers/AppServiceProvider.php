<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('bookings', function (Request $request) {
            return Limit::perMinute(20)->by(($request->user()?->id ?: $request->ip()).'|bookings');
        });

        RateLimiter::for('payment-proof', function (Request $request) {
            return Limit::perMinute(10)->by(($request->user()?->id ?: $request->ip()).'|proof');
        });

        RateLimiter::for('admin-owners', function (Request $request) {
            return Limit::perMinute(10)->by(($request->user()?->id ?: $request->ip()).'|admin-owners');
        });

        RateLimiter::for('forced-password', function (Request $request) {
            return Limit::perMinute(6)->by(($request->user()?->id ?: $request->ip()).'|forced-password');
        });

        RateLimiter::for('spaces-catalog', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip().'|spaces-catalog');
        });
    }
}
