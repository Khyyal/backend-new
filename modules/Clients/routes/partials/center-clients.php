<?php

use Illuminate\Support\Facades\Route;
use Modules\Clients\Http\Controllers\Center\ClientController;

/*
 * Shared by the center portal and the admin routes.
 */
Route::get('/', [ClientController::class, 'index'])->name('index');
Route::get('/lookup', [ClientController::class, 'lookup'])->name('lookup');
Route::post('/', [ClientController::class, 'store'])->name('store');
