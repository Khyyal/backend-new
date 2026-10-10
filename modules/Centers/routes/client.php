<?php

use Illuminate\Support\Facades\Route;
use Modules\Centers\Http\Controllers\Client\CenterController;
use Modules\Centers\Http\Controllers\Client\FavoriteController;
use Modules\Centers\Http\Controllers\Client\RatingController;

Route::middleware(['set.locale.from.accept.language'])
    ->prefix('clients')
    ->name('client.')
    ->group(function (): void {
        Route::get('/centers', [CenterController::class, 'index'])
            ->name('centers.index');

        Route::middleware(['track.client.session', 'auth:client'])
            ->group(function (): void {
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
