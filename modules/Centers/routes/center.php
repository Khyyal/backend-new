<?php

use Illuminate\Support\Facades\Route;
use Modules\Centers\Http\Controllers\Center\RegisterController;

Route::middleware(['api'])
    ->prefix('centers')
    ->name('centers.')
    ->group(function (): void {

        Route::middleware(['throttle:10,1'])
            ->group(function (): void {
                Route::post('/register', [RegisterController::class, 'store'])
                    ->name('register');
            });

    });
