<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAdminSessionIsFresh;
use App\Http\Middleware\EnsureAdminTwoFactorEnabled;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\PreventAdminIndexing;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            PreventAdminIndexing::class,
            SecurityHeaders::class,
        ]);

        $middleware->encryptCookies(except: [
            'wz_locale',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'active' => EnsureAccountIsActive::class,
            'password.changed' => EnsurePasswordIsChanged::class,
            'admin.idle' => EnsureAdminSessionIsFresh::class,
            'admin.2fa' => EnsureAdminTwoFactorEnabled::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/paypal',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, \Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            if (in_array($status, [403, 404, 419, 500, 503], true)) {
                return Inertia::render('Errors/Status', [
                    'status' => $status,
                ])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
