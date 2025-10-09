<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's route middleware aliases.
     *
     * These middleware may be assigned to groups or used individually.
     */
    protected $middlewareAliases = [
        // Laravel default
        'auth'       => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can'        => \Illuminate\Auth\Middleware\Authorize::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'precognitive'    => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        'throttle'        => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified'        => \App\Http\Middleware\EnsureEmailIsVerified::class,

        //إضافة الميدلوير ك
        'role' => \App\Http\Middleware\RoleMiddleware::class,
    ];
}
