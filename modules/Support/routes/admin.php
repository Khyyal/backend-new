<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\Admin\AuthController;
use Modules\Support\Http\Controllers\Admin\CityController;

Route::prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::prefix('auth')
            ->name('auth.')
            ->group(function () {
                Route::post('/login', [AuthController::class, 'login'])
                    ->middleware('throttle:10,1')
                    ->name('login');

                Route::get('/me', [AuthController::class, 'me'])
                    ->middleware('auth:system_user')
                    ->name('me');
            });

        Route::middleware('auth:system_user')
            ->apiResource('cities', CityController::class);
    });
