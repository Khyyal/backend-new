<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\MediaController;

require __DIR__.'/client.php';
require __DIR__.'/admin.php';
require __DIR__.'/center.php';

Route::middleware('auth:'.implode(',', config('support-media.guards')))
    ->prefix('media')
    ->name('media.')
    ->group(function () {
        Route::post('/', [MediaController::class, 'store'])->name('store');
        Route::delete('/{media:uuid}', [MediaController::class, 'destroy'])->name('destroy');
    });
