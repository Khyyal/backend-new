<?php

use Illuminate\Support\Facades\Route;
use Modules\Promotion\Http\Controllers\Admin\DiscountController as AdminDiscountController;

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api/admin/discounts')
    ->name('admin.discounts.')
    ->group(function (): void {
        Route::get('/', [AdminDiscountController::class, 'index'])
            ->name('index');
        Route::post('/', [AdminDiscountController::class, 'store'])
            ->name('store');
        Route::get('/{discount}', [AdminDiscountController::class, 'show'])
            ->name('show');
        Route::put('/{discount}', [AdminDiscountController::class, 'update'])
            ->name('update');
        Route::delete('/{discount}', [AdminDiscountController::class, 'destroy'])
            ->name('destroy');
        Route::patch('/{discount}/status', [AdminDiscountController::class, 'updateStatus'])
            ->name('status');

        Route::get('/{discount}/coupons', [AdminDiscountController::class, 'couponsIndex'])
            ->name('coupons.index');
        Route::post('/{discount}/coupons', [AdminDiscountController::class, 'couponsStore'])
            ->name('coupons.store');
        Route::delete('/{discount}/coupons/{coupon}', [AdminDiscountController::class, 'couponsDestroy'])
            ->name('coupons.destroy');
        Route::patch('/{discount}/coupons/{coupon}/status', [AdminDiscountController::class, 'couponsUpdateStatus'])
            ->name('coupons.status');

        Route::post('/{discount}/discountables', [AdminDiscountController::class, 'discountablesAttach'])
            ->name('discountables.attach');
        Route::delete('/{discount}/discountables/{discountableType}/{discountableId}', [AdminDiscountController::class, 'discountablesDetach'])
            ->where('discountableType', '.*')
            ->name('discountables.detach');
    });
