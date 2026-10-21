<?php

use Illuminate\Support\Facades\Route;
use Modules\Reservations\Http\Controllers\Center\ReservationController;

/*
 * Shared by the center portal and the admin routes.
 */
Route::get('/', [ReservationController::class, 'index'])->name('index');
Route::post('/', [ReservationController::class, 'store'])->name('store');
Route::get('/{reservation}', [ReservationController::class, 'show'])->name('show');
Route::patch('/{reservation}/reschedule', [ReservationController::class, 'reschedule'])->name('reschedule');
Route::post('/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('cancel');
Route::post('/{reservation}/complete', [ReservationController::class, 'complete'])->name('complete');
Route::post('/{reservation}/no-show', [ReservationController::class, 'noShow'])->name('no-show');
