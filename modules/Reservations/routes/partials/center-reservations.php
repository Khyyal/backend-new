<?php

use Illuminate\Support\Facades\Route;
use Modules\Reservations\Http\Controllers\Center\ReservationController;

/*
 * Shared by the center portal and the admin routes.
 */
Route::get('/', [ReservationController::class, 'index'])->name('index');
Route::post('/', [ReservationController::class, 'store'])->middleware(['throttle:coupon', 'idempotent'])->name('store');
Route::post('/quote', [ReservationController::class, 'quote'])->middleware('throttle:coupon')->name('quote');
Route::get('/calendar', [ReservationController::class, 'calendar'])->name('calendar');
Route::get('/code/{code}', [ReservationController::class, 'showByCode'])->name('show-by-code');
Route::get('/{reservation}', [ReservationController::class, 'show'])->name('show');
Route::patch('/{reservation}/reschedule', [ReservationController::class, 'reschedule'])->middleware('idempotent')->name('reschedule');
Route::post('/{reservation}/cancel', [ReservationController::class, 'cancel'])->middleware('idempotent')->name('cancel');
Route::post('/{reservation}/complete', [ReservationController::class, 'complete'])->middleware('idempotent')->name('complete');
Route::post('/{reservation}/no-show', [ReservationController::class, 'noShow'])->middleware('idempotent')->name('no-show');
