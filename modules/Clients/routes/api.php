<?php

use Illuminate\Support\Facades\Route;
use Modules\Clients\Http\Controllers\Client\AuthController;
use Modules\Clients\Http\Controllers\Client\FavoriteController;
use Modules\Clients\Http\Controllers\Client\ProfileController;
use Modules\Clients\Http\Controllers\Client\RatingController;

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

                    Route::post('/ratings', [RatingController::class, 'rate'])
                        ->name('ratings.rate');

                    Route::get('/favorites', [FavoriteController::class, 'index'])
                        ->name('favorites.index');
                    Route::post('/favorites', [FavoriteController::class, 'store'])
                        ->name('favorites.store');
                    Route::delete('/favorites/{center}', [FavoriteController::class, 'destroy'])
                        ->name('favorites.destroy');
                });
            });

        Route::post('/auth/login', [AuthController::class, 'login'])
            ->name('auth.login');

        Route::post('/auth/verify', [AuthController::class, 'verify'])
            ->name('auth.verify');
    });
