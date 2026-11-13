<?php

use Illuminate\Support\Facades\Route;
use Modules\Reservations\Http\Controllers\Center\HorseStayController;

/*
 * System-user access to a center's reservations. Reuses the center portal
 * controller; authorization goes through CenterPolicy::manageReservations.
 */
Route::prefix('admin/centers/{center}/reservations')
    ->name('admin.centers.reservations.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        require __DIR__.'/partials/center-reservations.php';
    });

Route::prefix('admin/centers/{center}/packages')
    ->name('admin.centers.packages.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        require __DIR__.'/partials/center-packages.php';
    });

Route::prefix('admin/centers/{center}/client-memberships')
    ->name('admin.centers.client-memberships.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        require __DIR__.'/partials/center-memberships.php';
    });

Route::prefix('admin/centers/{center}/horse-stays')
    ->name('admin.centers.horse-stays.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        Route::get('/', [HorseStayController::class, 'index'])->name('index');
        Route::get('/{reservation}', [HorseStayController::class, 'show'])->whereNumber('reservation')->name('show');
    });
