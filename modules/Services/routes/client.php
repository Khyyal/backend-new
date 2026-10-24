<?php

use Illuminate\Support\Facades\Route;
use Modules\Services\Http\Controllers\Client\ServiceController;

Route::middleware(['set.locale.from.accept.language'])
    ->prefix('clients')
    ->name('client.')
    ->group(function (): void {
        Route::get('/centers/{center}/services/{slug}', [ServiceController::class, 'show'])
            ->name('centers.services.show');
    });
