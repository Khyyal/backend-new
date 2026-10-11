<?php

use Illuminate\Support\Facades\Route;
use Modules\Promotion\Http\Controllers\Center\DiscountController;

Route::prefix('centers/{center}/discounts')
    ->name('centers.discounts.')
    ->middleware(['auth:center_user', 'center.scope'])
    ->scopeBindings()
    ->group(function (): void {
        Route::get('/', [DiscountController::class, 'index'])->name('index');
        Route::post('/', [DiscountController::class, 'store'])->name('store');
        Route::get('/{discount}', [DiscountController::class, 'show'])->name('show');
        Route::put('/{discount}', [DiscountController::class, 'update'])->name('update');
        Route::delete('/{discount}', [DiscountController::class, 'destroy'])->name('destroy');
        Route::patch('/{discount}/status', [DiscountController::class, 'updateStatus'])->name('status');

        Route::get('/{discount}/coupons', [DiscountController::class, 'couponsIndex'])->name('coupons.index');
        Route::post('/{discount}/coupons', [DiscountController::class, 'couponsStore'])->name('coupons.store');
        Route::delete('/{discount}/coupons/{coupon}', [DiscountController::class, 'couponsDestroy'])->name('coupons.destroy');
        Route::patch('/{discount}/coupons/{coupon}/status', [DiscountController::class, 'couponsUpdateStatus'])->name('coupons.status');

        Route::post('/{discount}/services', [DiscountController::class, 'servicesAttach'])->name('services.attach');
        Route::delete('/{discount}/services/{service}', [DiscountController::class, 'servicesDetach'])->name('services.detach');
    });
