<?php

use Illuminate\Support\Facades\Route;
use Modules\Homepage\Http\Controllers\Client\HomeController;

Route::middleware(['set.locale.from.accept.language'])
    ->prefix('clients')
    ->name('client.')
    ->group(function (): void {
        Route::get('/home', [HomeController::class, 'index'])->name('home.index');
        Route::get('/home/sections/{key}', [HomeController::class, 'section'])->name('home.sections.show');
        Route::post('/home/events', [HomeController::class, 'events'])->middleware('throttle:home-events')->name('home.events.store');
    });
