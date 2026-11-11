<?php

use Illuminate\Support\Facades\Route;
use Modules\Reservations\Http\Controllers\Center\PackageController;

/*
 * Shared by the center portal and the admin routes.
 */
Route::get('/', [PackageController::class, 'index'])->name('index');
Route::get('/counts', [PackageController::class, 'counts'])->name('counts');
Route::get('/{package}', [PackageController::class, 'show'])->name('show');
Route::patch('/{package}', [PackageController::class, 'update'])->name('update');
Route::post('/{package}/sessions', [PackageController::class, 'addSessions'])->name('sessions.store');
Route::post('/{package}/reservations', [PackageController::class, 'book'])->middleware('idempotent')->name('reservations.store');
