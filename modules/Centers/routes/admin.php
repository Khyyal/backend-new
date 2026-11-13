<?php

use Illuminate\Support\Facades\Route;
use Modules\Centers\Http\Controllers\Admin\CenterController;
use Modules\Centers\Http\Controllers\Admin\CenterUserController;
use Modules\Centers\Http\Controllers\Admin\TagController;
use Modules\Centers\Http\Controllers\Center\BlockedClientController;
use Modules\Centers\Http\Controllers\Center\HorseController;
use Modules\Centers\Http\Controllers\Center\PurchaseController;
use Modules\Centers\Http\Controllers\Center\RatingController;

Route::prefix('admin/centers')
    ->name('admin.centers.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        Route::get('/', [CenterController::class, 'index'])->name('index');
        Route::post('/', [CenterController::class, 'store'])->name('store');
        Route::post('/export', [CenterController::class, 'export'])->name('export');
        Route::get('/{center}', [CenterController::class, 'show'])->withTrashed()->name('show');
        Route::match(['put', 'patch'], '/{center}', [CenterController::class, 'update'])->name('update');
        Route::match(['put', 'patch'], '/{center}/social-links', [CenterController::class, 'updateSocialLinks'])->name('social-links.update');
        Route::match(['put', 'patch'], '/{center}/site-theme', [CenterController::class, 'updateSiteTheme'])->name('site-theme.update');
        Route::delete('/{center}', [CenterController::class, 'destroy'])->name('destroy');
        Route::post('/{center}/restore', [CenterController::class, 'restore'])->withTrashed()->name('restore');

        Route::get('/{center}/users', [CenterUserController::class, 'index'])->name('users.index');
        Route::get('/{center}/users/permissions', [CenterUserController::class, 'permissions'])->name('users.permissions');
        Route::post('/{center}/users', [CenterUserController::class, 'store'])->name('users.store');
        Route::get('/{center}/users/{user}', [CenterUserController::class, 'show'])->name('users.show')->whereNumber('user');
        Route::match(['put', 'patch'], '/{center}/users/{user}', [CenterUserController::class, 'update'])->name('users.update')->whereNumber('user');
        Route::delete('/{center}/users/{user}', [CenterUserController::class, 'destroy'])->name('users.destroy')->whereNumber('user');
        Route::post('/{center}/users/{user}/otp', [CenterUserController::class, 'issueOtp'])
            ->name('users.otp.store')
            ->whereNumber('user')
            ->middleware('throttle:10,1');

        Route::get('/{center}/ratings', [RatingController::class, 'index'])->name('ratings');

        Route::get('/{center}/blocked-clients', [BlockedClientController::class, 'index'])->name('blocked-clients.index');
        Route::post('/{center}/blocked-clients', [BlockedClientController::class, 'store'])->name('blocked-clients.store');
        Route::delete('/{center}/blocked-clients/{client}', [BlockedClientController::class, 'destroy'])->whereNumber('client')->name('blocked-clients.destroy');

        Route::get('/{center}/horses', [HorseController::class, 'index'])->name('horses.index');
        Route::post('/{center}/horses', [HorseController::class, 'store'])->name('horses.store');
        Route::get('/{center}/horses/{horse}', [HorseController::class, 'show'])->whereNumber('horse')->name('horses.show');
        Route::match(['put', 'patch'], '/{center}/horses/{horse}', [HorseController::class, 'update'])->whereNumber('horse')->name('horses.update');
        Route::delete('/{center}/horses/{horse}', [HorseController::class, 'destroy'])->whereNumber('horse')->name('horses.destroy');

        Route::get('/{center}/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::post('/{center}/purchases/export', [PurchaseController::class, 'export'])->name('purchases.export');
        Route::get('/{center}/purchases/code/{code}', [PurchaseController::class, 'showByCode'])->name('purchases.show-by-code');
        Route::get('/{center}/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
        Route::post('/{center}/purchases/{purchase}/refund', [PurchaseController::class, 'refund'])->middleware('idempotent')->name('purchases.refund');
    });

Route::prefix('admin')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        Route::apiResource('tags', TagController::class)->names('admin.tags');
    });
