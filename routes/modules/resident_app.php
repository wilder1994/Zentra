<?php

declare(strict_types=1);

use App\Http\Controllers\ResidentApp\AuthController;
use App\Http\Controllers\ResidentApp\AuthorizationController;
use App\Http\Controllers\ResidentApp\BlocklistController;
use App\Http\Controllers\ResidentApp\BookingController;
use App\Http\Controllers\ResidentApp\CorrespondenceController;
use App\Http\Controllers\ResidentApp\DeviceController;
use App\Http\Controllers\ResidentApp\HomeController;
use App\Http\Controllers\ResidentApp\ProfileController;
use App\Http\Controllers\ResidentApp\UnitController;
use Illuminate\Support\Facades\Route;

Route::prefix('r')->name('resident-app.')->group(function (): void {
    Route::middleware('guest:resident')->group(function (): void {
        Route::get('/login', [AuthController::class, 'create'])->name('login');
        Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    });

    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::middleware('auth:resident')->group(function (): void {
        Route::get('/', HomeController::class)->name('home');
        Route::get('/unidad', UnitController::class)->name('unit');
        Route::get('/autorizar', [AuthorizationController::class, 'index'])->name('authorizations');
        Route::get('/autorizar/nueva', [AuthorizationController::class, 'create'])->name('authorizations.create');
        Route::post('/autorizar', [AuthorizationController::class, 'store'])->name('authorizations.store');
        Route::delete('/autorizar/{authorization}', [AuthorizationController::class, 'destroy'])->name('authorizations.destroy');
        Route::get('/correspondencia', [CorrespondenceController::class, 'index'])->name('correspondence');
        Route::get('/reservas', [BookingController::class, 'index'])->name('bookings');
        Route::get('/reservas/nueva', [BookingController::class, 'create'])->name('bookings.create');
        Route::post('/reservas', [BookingController::class, 'store'])->name('bookings.store');
        Route::delete('/reservas/{booking}', [BookingController::class, 'destroy'])->whereNumber('booking')->name('bookings.destroy');
        Route::get('/bloqueos', BlocklistController::class)->name('blocklist');
        Route::get('/perfil', ProfileController::class)->name('profile');
        Route::post('/devices', [DeviceController::class, 'store'])->middleware('throttle:30,1')->name('devices.store');
    });
});
