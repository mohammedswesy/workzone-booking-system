<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\BookingController;

Route::get('/', function () {
    return view('welcome');
});


Route::resource('workspaces', WorkspaceController::class);


Route::get('/bookings', [BookingController::class, 'index'])->middleware('auth')->name('bookings.index');

use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserController;


Route::middleware(['auth'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::resource('/admin/users', UserController::class);
});


Route::middleware(['auth'])->resource('/admin/users', UserController::class);



Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
