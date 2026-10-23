<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/centers/{center}/clients')
    ->name('admin.centers.clients.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        require __DIR__.'/partials/center-clients.php';
    });
