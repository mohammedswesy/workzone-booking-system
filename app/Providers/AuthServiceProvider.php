<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Offer;
use App\Models\Workspace;
use App\Policies\BookingPolicy;
use App\Policies\OfferPolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Workspace::class => WorkspacePolicy::class,
        Booking::class => BookingPolicy::class,
        Offer::class => OfferPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
