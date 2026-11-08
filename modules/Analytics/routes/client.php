<?php

use Illuminate\Support\Facades\Route;
use Modules\Analytics\Http\Controllers\Client\EngagementController;

Route::prefix('clients')
    ->name('client.')
    ->group(function (): void {
        Route::post('/engagement/events', [EngagementController::class, 'store'])
            ->middleware('throttle:engagement-events')
            ->name('engagement.events.store');
    });
