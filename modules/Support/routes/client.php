<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\Client\CityController;
use Modules\Support\Http\Controllers\Client\LegalController;

Route::prefix('client')
    ->name('client.')
    ->group(function () {
        Route::get('/cities', [CityController::class, 'index'])
            ->name('cities.index');

        Route::get('/legal', LegalController::class)
            ->middleware('set.locale.from.accept.language')
            ->name('legal');
    });
