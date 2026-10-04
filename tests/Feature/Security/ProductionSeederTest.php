<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

it('does not seed demo passwords when the app environment is production', function () {
    $this->app->detectEnvironment(fn () => 'production');

    try {
        Artisan::call('db:seed', ['--force' => true]);

        expect(User::where('email', 'admin@example.com')->exists())->toBeFalse()
            ->and(User::where('email', 'owner@example.com')->exists())->toBeFalse()
            ->and(User::where('email', 'user@example.com')->exists())->toBeFalse();
    } finally {
        $this->app->detectEnvironment(fn () => 'testing');
    }
});
