<?php

use Illuminate\Support\Facades\Route;

Route::prefix('centers/{center}/reservations')
    ->name('centers.reservations.')
    ->middleware(['auth:center_user', 'center.scope'])
    ->group(function (): void {
        require __DIR__.'/partials/center-reservations.php';
    });

Route::prefix('centers/{center}/packages')
    ->name('centers.packages.')
    ->middleware(['auth:center_user', 'center.scope'])
    ->group(function (): void {
        require __DIR__.'/partials/center-packages.php';
    });
