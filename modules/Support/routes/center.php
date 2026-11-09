<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\Center\CityController;
use Modules\Support\Http\Controllers\Center\LegalController;

Route::prefix('center')
    ->name('center.')
    ->group(function () {
        Route::get('/cities', [CityController::class, 'index'])
            ->name('cities.index');

        Route::get('/legal', LegalController::class)
            ->middleware('set.locale.from.accept.language')
            ->name('legal');
    });
