<?php

use Illuminate\Support\Facades\Route;
use Modules\Reservations\Http\Controllers\Center\ReservationController;

/*
 * Shared by the center portal and the admin routes.
 */
Route::get('/', [ReservationController::class, 'index'])->name('index');
Route::post('/', [ReservationController::class, 'store'])->middleware(['throttle:coupon', 'idempotent'])->name('store');
Route::post('/stabling', [ReservationController::class, 'storeStabling'])->middleware('idempotent')->name('stabling.store');
Route::post('/quote', [ReservationController::class, 'quote'])->middleware('throttle:coupon')->name('quote');
Route::get('/calendar', [ReservationController::class, 'calendar'])->name('calendar');
Route::get('/code/{code}', [ReservationController::class, 'showByCode'])->name('show-by-code');
Route::get('/{reservation}', [ReservationController::class, 'show'])->name('show');
Route::put('/{reservation}/horse', [ReservationController::class, 'attachHorse'])->name('horse');
Route::patch('/{reservation}/reschedule', [ReservationController::class, 'reschedule'])->middleware('idempotent')->name('reschedule');
Route::post('/{reservation}/cancel', [ReservationController::class, 'cancel'])->middleware('idempotent')->name('cancel');
Route::post('/{reservation}/complete', [ReservationController::class, 'complete'])->middleware('idempotent')->name('complete');
Route::post('/{reservation}/end-stay', [ReservationController::class, 'endStay'])->middleware('idempotent')->name('end-stay');
Route::get('/{reservation}/invoices', [ReservationController::class, 'invoices'])->name('invoices.index');
Route::post('/{reservation}/invoices', [ReservationController::class, 'storeInvoice'])->middleware('idempotent')->name('invoices.store');
Route::post('/{reservation}/invoices/{invoice}/cancel', [ReservationController::class, 'cancelInvoice'])->whereNumber('invoice')->middleware('idempotent')->name('invoices.cancel');
Route::post('/{reservation}/no-show', [ReservationController::class, 'noShow'])->middleware('idempotent')->name('no-show');
