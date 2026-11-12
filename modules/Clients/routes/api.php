<?php

use Illuminate\Support\Facades\Route;
use Modules\Clients\Http\Controllers\Client\AuthController;
use Modules\Clients\Http\Controllers\Client\LaunchController;
use Modules\Clients\Http\Controllers\Client\PhoneController;
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
                    Route::post('/phone', [PhoneController::class, 'send'])
                        ->name('phone.send');
                    Route::post('/phone/verify', [PhoneController::class, 'verify'])
                        ->name('phone.verify');
                });
            });

        Route::post('/launch', LaunchController::class)
            ->middleware(['throttle:30,1'])
            ->name('launch');

        Route::post('/auth/login', [AuthController::class, 'login'])
            ->name('auth.login');

        Route::post('/auth/verify', [AuthController::class, 'verify'])
            ->name('auth.verify');
    });

require __DIR__.'/center.php';
require __DIR__.'/admin.php';
