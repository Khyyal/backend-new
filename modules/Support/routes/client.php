<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\Client\CityController;

Route::prefix('client')
    ->name('client.')
    ->group(function () {
        Route::get('/cities', [CityController::class, 'index'])
            ->name('cities.index');
    });

