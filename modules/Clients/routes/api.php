<?php

use Modules\Clients\Http\Controllers\Client\AuthController;
use Modules\Clients\Http\Controllers\Client\ProfileController;

Route::middleware(['api', 'set.locale.from.accept.language'])
    ->prefix('clients')
    ->name('client.')
    ->group(function (): void {


        Route::middleware(['track.client.session'])
            ->group(function (): void {
                Route::middleware(['auth:client'])->group(function (): void {
                    Route::match(['put', 'patch'], '/profile', [ProfileController::class, 'update'])
                        ->name('profile.update');
                    Route::get('/me', [ProfileController::class, 'me'])
                        ->name('profile.me');

                });
            });

        Route::post('/auth/login', [AuthController::class, 'login'])
            ->name('auth.login');

        Route::post('/auth/verify', [AuthController::class, 'verify'])
            ->name('auth.verify');
    });

