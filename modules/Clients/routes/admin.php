<?php

use Illuminate\Support\Facades\Route;
use Modules\Clients\Http\Controllers\Admin\ClientController;

Route::prefix('admin/centers/{center}/clients')
    ->name('admin.centers.clients.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        require __DIR__.'/partials/center-clients.php';
    });

Route::prefix('admin/clients')
    ->name('admin.clients.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        Route::get('/', [ClientController::class, 'index'])->name('index');
        Route::get('/search', [ClientController::class, 'search'])->name('search');
        Route::get('/{client}', [ClientController::class, 'show'])->name('show')->whereNumber('client');
    });
