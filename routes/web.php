<?php

use App\Http\Controllers\Owner\OfferController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Models (للإحصائيات في لوحة المالك)
use App\Models\Workspace;
use App\Models\Booking;
use App\Models\User;

// User namespace
use App\Http\Controllers\User\WorkspaceController as UserWorkspaceController;
use App\Http\Controllers\User\BookingController   as UserBookingController;

// Owner namespace
use App\Http\Controllers\Owner\WorkspaceController as OwnerWorkspaceController;
use App\Http\Controllers\Owner\BookingController   as OwnerBookingController;

// Admin namespace
use App\Http\Controllers\Admin\UserController      as AdminUserController;
use App\Http\Controllers\Admin\WorkspaceController as AdminWorkspaceController;
use App\Http\Controllers\Admin\BookingController   as AdminBookingController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| الصفحة الرئيسية
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => Inertia::render('Home'))->name('home');

/*
|--------------------------------------------------------------------------
| مسارات عامة (بدون تسجيل) — عرض المساحات (واجهة المستخدم)
|--------------------------------------------------------------------------
*/
Route::get('/spaces', [UserWorkspaceController::class, 'index'])->name('spaces.index');
Route::get('/spaces/{workspace}', [UserWorkspaceController::class, 'show'])->name('spaces.show');

/*
|--------------------------------------------------------------------------
| الحجز: شاشة إنشاء الحجز لمساحة محددة (يتطلب Auth)
| الوصول: /booking?workspace_id=123
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/booking', [UserBookingController::class, 'create'])->name('booking.create');
});

/*
|--------------------------------------------------------------------------
| /dashboard يوجّه حسب الدور
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', function () {
    $user = Auth::user();
    if (! $user) return redirect()->route('login');

    if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    return match ($user->role ?? 'user') {
        'admin' => redirect()->route('admin.dashboard'),
        'owner' => redirect()->route('owner.dashboard'),
        default => redirect()->route('user.bookings.index'),
    };
})->middleware('auth')->name('dashboard');

/*
|--------------------------------------------------------------------------
| صفحات البروفايل (Breeze) 

|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile',  [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| لوحات الأدمن
|--------------------------------------------------------------------------
*/
Route::middleware(['auth','role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Admin/Dashboard', [
                'stats' => [
                    'users'      => User::where('role','user')->count(),
                    'owners'     => User::where('role','owner')->count(),
                    'workspaces' => Workspace::count(),
                    'bookings'   => Booking::count(),
                ],
            ]);
        })->name('dashboard');

        Route::resource('users',      AdminUserController::class);
        Route::resource('workspaces', AdminWorkspaceController::class)
            ->parameters(['workspaces' => 'workspace']);
        Route::resource('bookings',   AdminBookingController::class)
            ->parameters(['bookings' => 'booking']);
             Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    });

/*
|--------------------------------------------------------------------------
| لوحة المالك (Owner)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth','role:owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        // داشبورد المالك مع إحصائيات
       Route::get('/dashboard', function () {
    $owner = request()->user();

    // KPIs
    $workspacesCount = Workspace::where('owner_id', $owner->id)->count();

    $bookingsCount = Booking::whereHas('workspace', function ($q) use ($owner) {
        $q->where('owner_id', $owner->id);
    })->count();

    $pendingCount = Booking::whereHas('workspace', function ($q) use ($owner) {
        $q->where('owner_id', $owner->id);
    })->where('status', 'pending')->count();

    // عدد العروض الفعّالة
    $activeOffersCount = \App\Models\Offer::whereHas('workspace', function ($q) use ($owner) {
        $q->where('owner_id', $owner->id);
    })->active()->count();

    // أعلى خصومات على مساحات المالك (Top 5)
    $topDiscounted = Workspace::query()
        ->where('owner_id', $owner->id)
        ->withCount([
            'activeOffers as max_discount' => function ($q) {
                $q->select(DB::raw('MAX(discount_percent)'));
            }
        ])
        ->orderByDesc('max_discount')
        ->take(5)
        ->get(['id','name','price_per_hour','image_url','location']);

    return Inertia::render('Owner/Dashboard', [
        'stats' => [
            'workspaces_count'   => $workspacesCount,
            'bookings_count'     => $bookingsCount,
            'pending_count'      => $pendingCount,
            'active_offers_count'=> $activeOffersCount,
        ],
        'topDiscounted' => $topDiscounted,
    ]);
})->name('dashboard');

        // مساحات المالك
        Route::resource('workspaces', OwnerWorkspaceController::class)
            ->parameters(['workspaces' => 'workspace']);

        // حجوزات مرتبطة بمساحات المالك (بدون create/store)
        Route::resource('bookings', OwnerBookingController::class)
            ->parameters(['bookings' => 'booking'])
            ->only(['index','show','edit','update','destroy']);

        Route::resource('offers', OfferController::class)
    ->except(['show'])
    ->parameters(['offers' => 'offer']);
    });

/*
|--------------------------------------------------------------------------
| مستخدم عادي (User)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth','role:user'])
    ->prefix('user')
    ->name('user.')
    ->group(function () {
        Route::resource('bookings', UserBookingController::class)
            ->parameters(['bookings' => 'booking']);
    });

/*
|--------------------------------------------------------------------------
| Auth scaffolding routes (Breeze)
|--------------------------------------------------------------------------
*/


// offer owner
use App\Http\Controllers\Owner\OfferController as OwnerOfferController;

Route::resource('offers', OwnerOfferController::class)->only(['index','create','store','edit','update','destroy']);

require __DIR__ . '/auth.php';
