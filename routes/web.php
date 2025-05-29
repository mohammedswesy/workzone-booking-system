<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserController;

Route::get('/', function () {
    return view('welcome');
});

// موارد Workspaces بدون middleware خاص
Route::resource('workspaces', WorkspaceController::class);

// صفحة الحجوزات فقط للمستخدمين المسجلين
Route::middleware('auth')->group(function () {
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::resource('bookings', BookingController::class);

    // صفحة الملف الشخصي
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// لوحات التحكم حسب الأدوار مع التحقق من تسجيل الدخول
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/users', [AdminController::class, 'manageUsers'])->name('admin.users');
    Route::get('/admin/bookings', [AdminController::class, 'manageBookings'])->name('admin.bookings');

    // إدارة المستخدمين - موارد كاملة
    Route::resource('/admin/users', UserController::class);
});

Route::middleware(['auth', 'role:owner'])->group(function () {
    Route::get('/owner/dashboard', [OwnerController::class, 'index'])->name('owner.dashboard');
});

Route::middleware(['auth', 'role:user'])->group(function () {
    Route::get('/user/dashboard', [UserController::class, 'index'])->name('user.dashboard');
});

// مناطق خاصة حسب الأدوار مع auth
Route::middleware(['auth', 'role:owner,admin'])->group(function () {
    Route::get('/owner-area', function () {
        return 'Welcome Owner or Admin';
    });
});

Route::middleware(['auth', 'role:user'])->group(function () {
    Route::get('/user-area', function () {
        return 'Welcome User';
    });
});

// صفحة لوحة التحكم العادية للمستخدمين مع التحقق من البريد
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/auth.php';
