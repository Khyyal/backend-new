<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\Docs\DocsLoginController;

Route::prefix('docs')->name('docs.')->group(function () {
    Route::get('/login', [DocsLoginController::class, 'show'])->name('login');
    Route::post('/login', [DocsLoginController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');
    Route::post('/logout', [DocsLoginController::class, 'logout'])
        ->middleware('auth:system_user_web')
        ->name('logout');
});
