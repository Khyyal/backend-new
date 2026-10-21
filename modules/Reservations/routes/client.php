<?php

use Illuminate\Support\Facades\Route;
use Modules\Reservations\Http\Controllers\Client\AvailabilityController;
use Modules\Reservations\Http\Controllers\Client\PackageController;
use Modules\Reservations\Http\Controllers\Client\ReservationController;

Route::middleware(['set.locale.from.accept.language', 'track.client.session', 'auth:client'])
    ->prefix('clients')
    ->name('client.')
    ->group(function (): void {
        Route::get('/services/{service}/availability', [AvailabilityController::class, 'show'])
            ->name('services.availability');

        Route::prefix('reservations')
            ->name('reservations.')
            ->group(function (): void {
                Route::get('/', [ReservationController::class, 'index'])->name('index');
                Route::post('/', [ReservationController::class, 'store'])->name('store');
                Route::get('/{reservation}', [ReservationController::class, 'show'])->name('show');
                Route::post('/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('cancel');
            });

        Route::prefix('packages')
            ->name('packages.')
            ->group(function (): void {
                Route::get('/', [PackageController::class, 'index'])->name('index');
                Route::post('/', [PackageController::class, 'store'])->name('store');
                Route::post('/{package}/reservations', [PackageController::class, 'book'])->name('reservations.store');
            });
    });
