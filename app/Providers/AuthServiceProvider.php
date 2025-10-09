<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

// موديلاتك
use App\Models\Workspace;
use App\Models\Booking;

// السياسات
use App\Policies\WorkspacePolicy;
use App\Policies\BookingPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Workspace::class => WorkspacePolicy::class,
        Booking::class   => BookingPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // لو عندك Gates مخصصة ممكن تضيفها هون
        // Gate::define('something', fn(User $user) => ...);
    }
}
