<?php

use Illuminate\Support\Facades\Route;
use Modules\Clients\Http\Controllers\Center\ClientController;

Route::prefix('centers/{center}/clients')
    ->name('centers.clients.')
    ->middleware(['auth:center_user', 'center.scope'])
    ->group(function (): void {
        require __DIR__.'/partials/center-clients.php';

        Route::post('/export', [ClientController::class, 'export'])->name('export');
    });
