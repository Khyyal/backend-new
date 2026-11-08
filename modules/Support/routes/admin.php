<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\Admin\ActivityLogController;
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

        Route::middleware('auth:system_user')->group(function () {
            Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
            Route::get('activity-logs/{activity}', [ActivityLogController::class, 'show'])
                ->whereNumber('activity')
                ->name('activity-logs.show');
        });
    });
