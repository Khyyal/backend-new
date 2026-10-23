<?php

use Illuminate\Support\Facades\Route;
use Modules\Centers\Http\Controllers\Admin\CenterController;
use Modules\Centers\Http\Controllers\Center\BlockedClientController;
use Modules\Centers\Http\Controllers\Center\PurchaseController;
use Modules\Centers\Http\Controllers\Center\RatingController;

Route::prefix('admin/centers')
    ->name('admin.centers.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        Route::get('/', [CenterController::class, 'index'])->name('index');
        Route::post('/', [CenterController::class, 'store'])->name('store');
        Route::get('/{center}', [CenterController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{center}', [CenterController::class, 'update'])->name('update');
        Route::delete('/{center}', [CenterController::class, 'destroy'])->name('destroy');

        Route::get('/{center}/ratings', [RatingController::class, 'index'])->name('ratings');

        Route::get('/{center}/blocked-clients', [BlockedClientController::class, 'index'])->name('blocked-clients.index');
        Route::post('/{center}/blocked-clients', [BlockedClientController::class, 'store'])->name('blocked-clients.store');

        Route::get('/{center}/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/{center}/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
    });
