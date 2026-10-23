<?php

use Illuminate\Support\Facades\Route;

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
