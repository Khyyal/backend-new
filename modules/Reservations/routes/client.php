<?php

use Illuminate\Support\Facades\Route;
use Modules\Reservations\Http\Controllers\Client\AvailabilityController;
use Modules\Reservations\Http\Controllers\Client\BookingController;
use Modules\Reservations\Http\Controllers\Client\MembershipController;
use Modules\Reservations\Http\Controllers\Client\PackageController;
use Modules\Reservations\Http\Controllers\Client\PurchaseController;
use Modules\Reservations\Http\Controllers\Client\ReservationController;

Route::middleware(['set.locale.from.accept.language', 'track.client.session', 'auth:client'])
    ->prefix('clients')
    ->name('client.')
    ->group(function (): void {
        Route::get('/services/{service}/availability', [AvailabilityController::class, 'show'])
            ->name('services.availability');

        Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');

        Route::prefix('reservations')
            ->name('reservations.')
            ->group(function (): void {
                Route::get('/', [ReservationController::class, 'index'])->name('index');
                Route::post('/', [ReservationController::class, 'store'])->middleware(['throttle:coupon', 'idempotent'])->name('store');
                Route::post('/quote', [ReservationController::class, 'quote'])->middleware('throttle:coupon')->name('quote');
                Route::get('/code/{code}', [ReservationController::class, 'showByCode'])->name('show-by-code');
                Route::get('/{reservation}', [ReservationController::class, 'show'])->name('show');
                Route::post('/{reservation}/cancel', [ReservationController::class, 'cancel'])->middleware('idempotent')->name('cancel');
            });

        Route::prefix('packages')
            ->name('packages.')
            ->group(function (): void {
                Route::get('/', [PackageController::class, 'index'])->name('index');
                Route::post('/', [PackageController::class, 'store'])->middleware(['throttle:coupon', 'idempotent'])->name('store');
                Route::post('/quote', [PackageController::class, 'quote'])->middleware('throttle:coupon')->name('quote');
                Route::patch('/{package}', [PackageController::class, 'update'])->name('update');
                Route::post('/{package}/reservations', [PackageController::class, 'book'])->middleware('idempotent')->name('reservations.store');
            });

        Route::get('/purchases/code/{code}', [PurchaseController::class, 'showByCode'])->name('purchases.show-by-code');

        Route::prefix('memberships')
            ->name('memberships.')
            ->group(function (): void {
                Route::get('/', [MembershipController::class, 'index'])->name('index');
                Route::post('/', [MembershipController::class, 'store'])->middleware(['throttle:coupon', 'idempotent'])->name('store');
                Route::post('/quote', [MembershipController::class, 'quote'])->middleware('throttle:coupon')->name('quote');
                Route::post('/{membership}/visits', [MembershipController::class, 'bookVisit'])->middleware('idempotent')->name('visits.store');
                Route::post('/{membership}/invites', [MembershipController::class, 'invite'])->middleware('idempotent')->name('invites.store');
            });
    });
