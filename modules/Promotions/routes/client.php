<?php

use Illuminate\Support\Facades\Route;
use Modules\Promotions\Http\Controllers\Client\OfferController;

Route::middleware(['set.locale.from.accept.language', 'track.client.session', 'auth:client'])
    ->prefix('clients')
    ->name('client.')
    ->group(function (): void {
        Route::get('/offers', [OfferController::class, 'index'])->name('offers.index');
        Route::get('/offers/{promotion}', [OfferController::class, 'show'])->name('offers.show');
    });
