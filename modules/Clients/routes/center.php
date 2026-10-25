<?php

use Illuminate\Support\Facades\Route;

Route::prefix('centers/{center}/clients')
    ->name('centers.clients.')
    ->middleware(['auth:center_user', 'center.scope'])
    ->group(function (): void {
        require __DIR__.'/partials/center-clients.php';
    });
