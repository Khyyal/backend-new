<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\Center\CityController;

Route::prefix('center')
    ->name('center.')
    ->group(function () {
        Route::get('/cities', [CityController::class, 'index'])
            ->name('cities.index');
    });

