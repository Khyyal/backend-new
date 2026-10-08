<?php

use Illuminate\Support\Facades\Route;
use Modules\Centers\Http\Controllers\Client\CenterController;

Route::middleware(['set.locale.from.accept.language'])
    ->prefix('clients')
    ->name('client.')
    ->group(function (): void {
        Route::get('/centers', [CenterController::class, 'index'])
            ->name('centers.index');
    });
