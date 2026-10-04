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
})->middleware('auth')->name('dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'active', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

        Route::resource('users', AdminUserController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::post('users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::post('users/{user}/reactivate', [AdminUserController::class, 'reactivate'])->name('users.reactivate');
        Route::post('users/{user}/resend-invitation', [AdminUserController::class, 'resendInvitation'])->name('users.resend-invitation');

        Route::resource('workspaces', AdminWorkspaceController::class)
            ->parameters(['workspaces' => 'workspace'])
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::resource('bookings', AdminBookingController::class)
            ->parameters(['bookings' => 'booking'])
            ->only(['index', 'show', 'edit', 'update', 'destroy']);
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

Route::middleware(['auth', 'active', 'role:owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        Route::get('/dashboard', OwnerDashboardController::class)->name('dashboard');

        Route::resource('workspaces', OwnerWorkspaceController::class)
            ->parameters(['workspaces' => 'workspace']);

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
    });

Route::middleware(['auth', 'active', 'role:user'])
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
        ->name('payments.manual.confirm');
    Route::post('/payments/{payment}/reject', [PaymentController::class, 'rejectManual'])
        ->name('payments.manual.reject');
});

Route::post('/webhooks/paypal', [PaymentController::class, 'webhookPaypal'])->name('webhooks.paypal');

require __DIR__.'/auth.php';
