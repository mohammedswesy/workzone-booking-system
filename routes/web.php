<?php

use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\WorkspaceController as AdminWorkspaceController;
use App\Http\Controllers\Owner\BookingController as OwnerBookingController;
use App\Http\Controllers\Owner\OfferController;
use App\Http\Controllers\Owner\WorkspaceController as OwnerWorkspaceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\User\BookingController as UserBookingController;
use App\Http\Controllers\User\WorkspaceController as UserWorkspaceController;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'))->name('home');

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
        default => redirect()->route('user.bookings.index'),
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
        Route::get('/dashboard', function () {
            return Inertia::render('Admin/Dashboard', [
                'stats' => [
                    'users' => User::where('role', 'user')->count(),
                    'owners' => User::where('role', 'owner')->count(),
                    'workspaces' => Workspace::count(),
                    'bookings' => Booking::count(),
                ],
            ]);
        })->name('dashboard');

        Route::resource('users', AdminUserController::class)->only(['index', 'edit', 'update', 'destroy']);
        Route::resource('workspaces', AdminWorkspaceController::class)
            ->parameters(['workspaces' => 'workspace'])
            ->only(['index', 'edit', 'update', 'destroy']);
        Route::resource('bookings', AdminBookingController::class)
            ->parameters(['bookings' => 'booking'])
            ->only(['index', 'show', 'edit', 'update', 'destroy']);
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    });

Route::middleware(['auth', 'role:owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        Route::get('/dashboard', function () {
            $owner = request()->user();

            $workspacesCount = Workspace::where('owner_id', $owner->id)->count();

            $bookingsCount = Booking::whereHas('workspace', function ($q) use ($owner) {
                $q->where('owner_id', $owner->id);
            })->count();

            $pendingCount = Booking::whereHas('workspace', function ($q) use ($owner) {
                $q->where('owner_id', $owner->id);
            })->where('status', 'pending')->count();

            $activeOffersCount = Offer::whereHas('workspace', function ($q) use ($owner) {
                $q->where('owner_id', $owner->id);
            })->active()->count();

            $topDiscounted = Workspace::query()
                ->where('owner_id', $owner->id)
                ->withCount([
                    'activeOffers as max_discount' => function ($q) {
                        $q->select(DB::raw('MAX(discount_percent)'));
                    },
                ])
                ->orderByDesc('max_discount')
                ->take(5)
                ->get(['id', 'name', 'price_per_hour', 'image_url', 'location']);

            return Inertia::render('Owner/Dashboard', [
                'stats' => [
                    'workspaces_count' => $workspacesCount,
                    'bookings_count' => $bookingsCount,
                    'pending_count' => $pendingCount,
                    'active_offers_count' => $activeOffersCount,
                ],
                'topDiscounted' => $topDiscounted,
            ]);
        })->name('dashboard');

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
        Route::resource('bookings', UserBookingController::class)
            ->parameters(['bookings' => 'booking']);
    });

require __DIR__.'/auth.php';
