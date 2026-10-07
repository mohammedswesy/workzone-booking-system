<?php

use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OwnerAccountController;
use App\Http\Controllers\Admin\PayoutController as AdminPayoutController;
use App\Http\Controllers\Admin\PlatformPaymentMethodController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VenueController as AdminVenueController;
use App\Http\Controllers\Admin\WorkspaceController as AdminWorkspaceController;
use App\Http\Controllers\Owner\AvailabilityController as OwnerAvailabilityController;
use App\Http\Controllers\Owner\BookingController as OwnerBookingController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Owner\OfferController;
use App\Http\Controllers\Owner\PayoutController as OwnerPayoutController;
use App\Http\Controllers\Owner\VenueController as OwnerVenueController;
use App\Http\Controllers\Owner\WorkspaceController as OwnerWorkspaceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\User\BookingController as UserBookingController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use App\Http\Controllers\User\WorkspaceController as UserWorkspaceController;
use App\Models\Venue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $featured = Venue::query()
        ->published()
        ->featured()
        ->whereHas('units', fn ($q) => $q->where('status', \App\Enums\WorkspaceStatus::Published))
        ->with([
            'place:id,name,city',
            'amenities:id,name',
            'images' => fn ($q) => $q->orderBy('sort_order')->limit(1),
            'units' => fn ($q) => $q->where('status', \App\Enums\WorkspaceStatus::Published)->limit(6),
        ])
        ->latest()
        ->take(3)
        ->get()
        ->map(function (Venue $venue) {
            $from = $venue->units->min('price_per_hour');

            return [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'featured' => (bool) $venue->featured,
                'cover_image_url' => $venue->cover_image_url,
                'place' => $venue->place,
                'amenities' => $venue->amenities,
                'unit_count' => $venue->units->count(),
                'from_price' => $from !== null ? (float) $from : null,
            ];
        })
        ->values();

    return Inertia::render('Home', [
        'featured' => $featured,
    ]);
})->name('home');

Route::get('/spaces', [UserWorkspaceController::class, 'index'])
    ->middleware('throttle:spaces-catalog')
    ->name('spaces.index');
Route::get('/spaces/map.json', \App\Http\Controllers\User\VenueMapController::class)
    ->middleware('throttle:spaces-catalog')
    ->name('spaces.map');
Route::get('/spaces/{space}', [UserWorkspaceController::class, 'show'])->name('spaces.show');

Route::get('/account/suspended', fn () => \Inertia\Inertia::render('Errors/Suspended'))
    ->name('account.suspended');

Route::get('/dashboard', function () {
    $user = Auth::user();
    if (! $user) {
        return redirect()->route('login');
    }

    if ($user->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    return match ($user->role?->value ?? 'user') {
        'admin' => redirect()->route('admin.dashboard'),
        'owner' => redirect()->route('owner.dashboard'),
        default => redirect()->route('user.dashboard'),
    };
})->middleware(['auth', 'active', 'password.changed'])->name('dashboard');

Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'active', 'password.changed', 'role:admin', 'admin.idle', 'admin.2fa'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

        Route::get('two-factor/setup', [\App\Http\Controllers\Admin\TwoFactorController::class, 'setup'])->name('two-factor.setup');
        Route::post('two-factor/confirm', [\App\Http\Controllers\Admin\TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
        Route::get('two-factor/challenge', [\App\Http\Controllers\Admin\TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
        Route::post('two-factor/verify', [\App\Http\Controllers\Admin\TwoFactorController::class, 'verify'])->name('two-factor.verify');

        Route::resource('users', AdminUserController::class)->only(['index', 'create', 'store', 'edit', 'destroy']);
        Route::match(['put', 'patch'], 'users/{user}', [AdminUserController::class, 'update'])
            ->middleware('password.confirm')
            ->name('users.update');
        Route::post('users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::post('users/{user}/reactivate', [AdminUserController::class, 'reactivate'])->name('users.reactivate');
        Route::post('users/{user}/resend-invitation', [AdminUserController::class, 'resendInvitation'])->name('users.resend-invitation');
        Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])
            ->middleware(['throttle:admin-owners', 'password.confirm'])
            ->name('users.reset-password');
        Route::post('owners', [\App\Http\Controllers\Admin\OwnerController::class, 'store'])
            ->middleware('throttle:admin-owners')
            ->name('owners.store');

        Route::resource('workspaces', AdminWorkspaceController::class)
            ->parameters(['workspaces' => 'workspace'])
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

        Route::resource('venues', AdminVenueController::class)
            ->parameters(['venues' => 'venue']);
        Route::post('venues/{venue}/transfer-owner', [AdminVenueController::class, 'transferOwner'])
            ->middleware('password.confirm')
            ->name('venues.transfer-owner');
        Route::post('venues/{venue}/images', [AdminVenueController::class, 'storeImages'])->name('venues.images.store');
        Route::post('venues/{venue}/images/{image}/primary', [AdminVenueController::class, 'setPrimaryImage'])->name('venues.images.primary');
        Route::put('venues/{venue}/images/reorder', [AdminVenueController::class, 'reorderImages'])->name('venues.images.reorder');
        Route::put('venues/{venue}/images/{image}/caption', [AdminVenueController::class, 'updateImageCaption'])->name('venues.images.caption');
        Route::delete('venues/{venue}/images/{image}', [AdminVenueController::class, 'destroyImage'])->name('venues.images.destroy');
        Route::get('venues/{venue}/units/create', [AdminVenueController::class, 'createUnit'])->name('venues.units.create');
        Route::post('venues/{venue}/units', [AdminVenueController::class, 'storeUnit'])->name('venues.units.store');
        Route::get('venues/{venue}/units/{workspace}/edit', [AdminVenueController::class, 'editUnit'])->name('venues.units.edit');
        Route::put('venues/{venue}/units/{workspace}', [AdminVenueController::class, 'updateUnit'])->name('venues.units.update');
        Route::delete('venues/{venue}/units/{workspace}', [AdminVenueController::class, 'destroyUnit'])->name('venues.units.destroy');
        Route::get('venues/{venue}/availability', [OwnerAvailabilityController::class, 'edit'])->name('venues.availability.edit');
        Route::put('venues/{venue}/availability/hours', [OwnerAvailabilityController::class, 'updateHours'])->name('venues.availability.hours');
        Route::put('venues/{venue}/units/{workspace}/availability/hours', [OwnerAvailabilityController::class, 'updateUnitHours'])->name('venues.units.availability.hours');
        Route::put('venues/{venue}/availability/pause', [OwnerAvailabilityController::class, 'updatePause'])->name('venues.availability.pause');
        Route::post('venues/{venue}/availability/exceptions', [OwnerAvailabilityController::class, 'storeException'])->name('venues.availability.exceptions.store');
        Route::delete('venues/{venue}/availability/exceptions/{exception}', [OwnerAvailabilityController::class, 'destroyException'])->name('venues.availability.exceptions.destroy');
        Route::get('venues/{venue}/availability/conflicts', [OwnerAvailabilityController::class, 'previewConflicts'])->name('venues.availability.conflicts');

        Route::resource('bookings', AdminBookingController::class)
            ->parameters(['bookings' => 'booking'])
            ->only(['index', 'show', 'edit', 'update', 'destroy']);

        Route::get('platform-payments', [PlatformPaymentMethodController::class, 'index'])->name('platform-payments.index');
        Route::post('platform-payments', [PlatformPaymentMethodController::class, 'store'])
            ->middleware('password.confirm')
            ->name('platform-payments.store');
        Route::post('platform-payments/settings', [PlatformPaymentMethodController::class, 'updateSettings'])
            ->middleware('password.confirm')
            ->name('platform-payments.settings');
        Route::post('platform-payments/{platformPaymentMethod}', [PlatformPaymentMethodController::class, 'update'])
            ->middleware('password.confirm')
            ->name('platform-payments.update');
        Route::delete('platform-payments/{platformPaymentMethod}', [PlatformPaymentMethodController::class, 'destroy'])
            ->middleware('password.confirm')
            ->name('platform-payments.destroy');

        Route::get('payouts', [AdminPayoutController::class, 'index'])->name('payouts.index');
        Route::get('payouts/{payout}', [AdminPayoutController::class, 'show'])->name('payouts.show');
        Route::get('payouts/{payout}/statement', [AdminPayoutController::class, 'statement'])->name('payouts.statement');
        Route::get('payouts/{payout}/statement.csv', [AdminPayoutController::class, 'exportCsv'])->name('payouts.statement.csv');
        Route::post('payouts/{payout}/approve', [AdminPayoutController::class, 'approve'])
            ->middleware('password.confirm')
            ->name('payouts.approve');
        Route::post('payouts/{payout}/pay', [AdminPayoutController::class, 'pay'])
            ->middleware('password.confirm')
            ->name('payouts.pay');
        Route::post('payouts/{payout}/reject', [AdminPayoutController::class, 'reject'])
            ->middleware('password.confirm')
            ->name('payouts.reject');
        Route::post('owners/{owner}/ledger-adjustments', [AdminPayoutController::class, 'adjust'])
            ->middleware('password.confirm')
            ->name('owners.ledger-adjust');

        Route::get('owner-accounts', [OwnerAccountController::class, 'index'])->name('owner-accounts.index');
        Route::get('owner-accounts/{owner}', [OwnerAccountController::class, 'show'])->name('owner-accounts.show');
        Route::get('owner-accounts/{owner}/summary', [OwnerAccountController::class, 'summary'])->name('owner-accounts.summary');
        Route::get('owner-accounts/{owner}/export.csv', [OwnerAccountController::class, 'exportCsv'])->name('owner-accounts.export');

        Route::get('audit-logs', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/export.csv', [\App\Http\Controllers\Admin\AuditLogController::class, 'export'])->name('audit-logs.export');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

Route::middleware(['auth', 'active', 'password.changed', 'role:owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        Route::get('/dashboard', OwnerDashboardController::class)->name('dashboard');

        Route::resource('venues', OwnerVenueController::class)
            ->parameters(['venues' => 'venue']);
        Route::post('venues/{venue}/images', [OwnerVenueController::class, 'storeImages'])->name('venues.images.store');
        Route::post('venues/{venue}/images/{image}/primary', [OwnerVenueController::class, 'setPrimaryImage'])->name('venues.images.primary');
        Route::put('venues/{venue}/images/reorder', [OwnerVenueController::class, 'reorderImages'])->name('venues.images.reorder');
        Route::put('venues/{venue}/images/{image}/caption', [OwnerVenueController::class, 'updateImageCaption'])->name('venues.images.caption');
        Route::delete('venues/{venue}/images/{image}', [OwnerVenueController::class, 'destroyImage'])->name('venues.images.destroy');
        Route::get('venues/{venue}/units/create', [OwnerVenueController::class, 'createUnit'])->name('venues.units.create');
        Route::post('venues/{venue}/units', [OwnerVenueController::class, 'storeUnit'])->name('venues.units.store');
        Route::get('venues/{venue}/units/{workspace}/edit', [OwnerVenueController::class, 'editUnit'])->name('venues.units.edit');
        Route::put('venues/{venue}/units/{workspace}', [OwnerVenueController::class, 'updateUnit'])->name('venues.units.update');
        Route::delete('venues/{venue}/units/{workspace}', [OwnerVenueController::class, 'destroyUnit'])->name('venues.units.destroy');
        Route::get('venues/{venue}/availability', [OwnerAvailabilityController::class, 'edit'])->name('venues.availability.edit');
        Route::put('venues/{venue}/availability/hours', [OwnerAvailabilityController::class, 'updateHours'])->name('venues.availability.hours');
        Route::put('venues/{venue}/units/{workspace}/availability/hours', [OwnerAvailabilityController::class, 'updateUnitHours'])->name('venues.units.availability.hours');
        Route::put('venues/{venue}/availability/pause', [OwnerAvailabilityController::class, 'updatePause'])->name('venues.availability.pause');
        Route::post('venues/{venue}/availability/exceptions', [OwnerAvailabilityController::class, 'storeException'])->name('venues.availability.exceptions.store');
        Route::delete('venues/{venue}/availability/exceptions/{exception}', [OwnerAvailabilityController::class, 'destroyException'])->name('venues.availability.exceptions.destroy');
        Route::get('venues/{venue}/availability/conflicts', [OwnerAvailabilityController::class, 'previewConflicts'])->name('venues.availability.conflicts');

        Route::resource('workspaces', OwnerWorkspaceController::class)
            ->parameters(['workspaces' => 'workspace'])
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

        Route::post('workspaces/{workspace}/images/{image}/primary', [OwnerWorkspaceController::class, 'setPrimaryImage'])
            ->name('workspaces.images.primary');
        Route::put('workspaces/{workspace}/images/reorder', [OwnerWorkspaceController::class, 'reorderImages'])
            ->name('workspaces.images.reorder');
        Route::delete('workspaces/{workspace}/images/{image}', [OwnerWorkspaceController::class, 'destroyImage'])
            ->name('workspaces.images.destroy');

        Route::resource('bookings', OwnerBookingController::class)
            ->parameters(['bookings' => 'booking'])
            ->only(['index', 'show', 'edit', 'update', 'destroy']);

        Route::resource('offers', OfferController::class)
            ->except(['show'])
            ->parameters(['offers' => 'offer']);

        Route::get('payouts', [OwnerPayoutController::class, 'index'])->name('payouts.index');
        Route::post('payouts', [OwnerPayoutController::class, 'store'])->name('payouts.store');
        Route::get('payouts/{payout}/statement', [OwnerPayoutController::class, 'statement'])->name('payouts.statement');
        Route::get('payouts/{payout}/statement.csv', [OwnerPayoutController::class, 'exportCsv'])->name('payouts.statement.csv');
    });

Route::middleware(['auth', 'active', 'password.changed', 'role:user'])
    ->prefix('user')
    ->name('user.')
    ->group(function () {
        Route::get('/dashboard', UserDashboardController::class)->name('dashboard');

        Route::get('bookings/availability', [UserBookingController::class, 'availability'])
            ->middleware('throttle:bookings')
            ->name('bookings.availability');

        Route::resource('bookings', UserBookingController::class)
            ->parameters(['bookings' => 'booking'])
            ->middleware(['throttle:bookings']);

        Route::post('bookings/{booking}/payments/manual', [PaymentController::class, 'storeManual'])
            ->middleware('throttle:payment-proof')
            ->name('payments.manual.store');
        Route::post('bookings/{booking}/payments/paypal', [PaymentController::class, 'storePaypal'])
            ->middleware('throttle:payment-proof')
            ->name('payments.paypal.store');
    });

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/payments/paypal/return', [PaymentController::class, 'paypalReturn'])->name('payments.paypal.return');
    Route::get('/payments/paypal/cancel', [PaymentController::class, 'paypalCancel'])->name('payments.paypal.cancel');
    Route::get('/payments/{payment}/proof', [PaymentController::class, 'showProof'])
        ->name('payments.proof.show');
    Route::post('/payments/{payment}/confirm', [PaymentController::class, 'confirmManual'])
        ->middleware('password.confirm')
        ->name('payments.manual.confirm');
    Route::post('/payments/{payment}/reject', [PaymentController::class, 'rejectManual'])
        ->middleware('password.confirm')
        ->name('payments.manual.reject');
});

Route::post('/webhooks/paypal', [PaymentController::class, 'webhookPaypal'])->name('webhooks.paypal');

require __DIR__.'/auth.php';
