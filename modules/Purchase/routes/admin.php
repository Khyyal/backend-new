<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchase\Http\Controllers\Admin\FeeRuleController;
use Modules\Purchase\Http\Controllers\Admin\PurchaseController;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->whereNumber('purchase')->name('purchases.show');
        Route::apiResource('fee-rules', FeeRuleController::class);
    });
