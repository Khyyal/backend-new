<?php

use Illuminate\Support\Facades\Route;
use Modules\Reservations\Http\Controllers\Center\PackageController;

/*
 * Shared by the center portal and the admin routes.
 */
Route::get('/', [PackageController::class, 'index'])->name('index');
Route::get('/counts', [PackageController::class, 'counts'])->name('counts');
