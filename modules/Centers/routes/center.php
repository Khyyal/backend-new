<?php

use Illuminate\Support\Facades\Route;
use Modules\Centers\Http\Controllers\Center\AuthController;
use Modules\Centers\Http\Controllers\Center\CenterController;
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

        Route::prefix('auth')
            ->name('auth.')
            ->group(function (): void {
                Route::middleware(['throttle:10,1'])
                    ->group(function (): void {
                        Route::post('/phone', [AuthController::class, 'login'])
                            ->name('phone.send');
                    });

                Route::post('/phone/verify', [AuthController::class, 'verify'])
                    ->name('phone.verify');
            });

        Route::middleware(['auth:center_user'])
            ->group(function (): void {
                Route::get('/', [CenterController::class, 'index'])
                    ->name('index');

                Route::get('/{center}', [CenterController::class, 'show'])
                    ->name('show');

                Route::post('/{center}/access', [CenterController::class, 'access'])
                    ->name('access');
            });

    });
