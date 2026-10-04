<?php

use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\WorkspaceController as AdminWorkspaceController;
use App\Http\Controllers\Owner\BookingController as OwnerBookingController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Owner\OfferController;
use App\Http\Controllers\Owner\WorkspaceController as OwnerWorkspaceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\User\BookingController as UserBookingController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use App\Http\Controllers\User\WorkspaceController as UserWorkspaceController;
use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', [
        'featured' => Workspace::query()
            ->published()
            ->featured()
            ->with([
                'place:id,name,city',
                'amenities:id,name',
                'images' => fn ($q) => $q->orderBy('sort_order')->limit(1),
                'activeOffers',
            ])
            ->latest()
            ->take(3)
            ->get(),
    ]);
})->name('home');

Route::get('/spaces', [UserWorkspaceController::class, 'index'])->name('spaces.index');
Route::get('/spaces/{workspace}', [UserWorkspaceController::class, 'show'])->name('spaces.show');

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
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

        Route::resource('users', AdminUserController::class)->only(['index', 'edit', 'update', 'destroy']);
        Route::resource('workspaces', AdminWorkspaceController::class)
            ->parameters(['workspaces' => 'workspace'])
            ->only(['index', 'edit', 'update', 'destroy']);
        Route::resource('bookings', AdminBookingController::class)
            ->parameters(['bookings' => 'booking'])
            ->only(['index', 'show', 'edit', 'update', 'destroy']);
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

Route::middleware(['auth', 'role:owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        Route::get('/dashboard', OwnerDashboardController::class)->name('dashboard');

        Route::resource('workspaces', OwnerWorkspaceController::class)
            ->parameters(['workspaces' => 'workspace']);

        Route::resource('bookings', OwnerBookingController::class)
            ->parameters(['bookings' => 'booking'])
            ->only(['index', 'show', 'edit', 'update', 'destroy']);

        Route::resource('offers', OfferController::class)
            ->except(['show'])
            ->parameters(['offers' => 'offer']);
    });

Route::middleware(['auth', 'role:user'])
    ->prefix('user')
    ->name('user.')
    ->group(function () {
        Route::get('/dashboard', UserDashboardController::class)->name('dashboard');

        Route::resource('bookings', UserBookingController::class)
            ->parameters(['bookings' => 'booking']);

        Route::post('bookings/{booking}/payments/manual', [PaymentController::class, 'storeManual'])
            ->name('payments.manual.store');
        Route::post('bookings/{booking}/payments/paypal', [PaymentController::class, 'storePaypal'])
            ->name('payments.paypal.store');
    });

Route::middleware('auth')->group(function () {
    Route::get('/payments/paypal/return', [PaymentController::class, 'paypalReturn'])->name('payments.paypal.return');
    Route::get('/payments/paypal/cancel', [PaymentController::class, 'paypalCancel'])->name('payments.paypal.cancel');
    Route::post('/payments/{payment}/confirm', [PaymentController::class, 'confirmManual'])
        ->name('payments.manual.confirm');
});

Route::post('/webhooks/paypal', [PaymentController::class, 'webhookPaypal'])->name('webhooks.paypal');

require __DIR__.'/auth.php';
