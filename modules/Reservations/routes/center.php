<?php

use Illuminate\Support\Facades\Route;

Route::prefix('centers/{center}/reservations')
    ->name('centers.reservations.')
    ->middleware(['auth:center_user', 'center.scope'])
    ->group(function (): void {
        require __DIR__.'/partials/center-reservations.php';
    });
