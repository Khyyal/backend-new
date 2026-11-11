<?php

use Illuminate\Support\Facades\Route;
use Modules\Reservations\Http\Controllers\Center\MembershipController;

/*
 * Shared by the center portal and the admin routes.
 */
Route::get('/', [MembershipController::class, 'index'])->name('index');
Route::get('/counts', [MembershipController::class, 'counts'])->name('counts');
Route::get('/{membership}', [MembershipController::class, 'show'])->name('show');
Route::patch('/{membership}', [MembershipController::class, 'update'])->name('update');
