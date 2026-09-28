<?php

use Illuminate\Support\Facades\Route;
use Modules\Promotion\Http\Controllers\Center\DiscountController as CenterDiscountController;

Route::middleware(['api', 'auth:center_user'])
    ->prefix('api/center/discounts')
    ->name('center.discounts.')
    ->group(function (): void {
        Route::get('/', [CenterDiscountController::class, 'index'])
            ->name('index');
        Route::post('/', [CenterDiscountController::class, 'store'])
            ->name('store');
        Route::get('/{discount}', [CenterDiscountController::class, 'show'])
            ->name('show');
        Route::put('/{discount}', [CenterDiscountController::class, 'update'])
            ->name('update');
        Route::delete('/{discount}', [CenterDiscountController::class, 'destroy'])
            ->name('destroy');
        Route::patch('/{discount}/status', [CenterDiscountController::class, 'updateStatus'])
            ->name('status');

        Route::get('/{discount}/coupons', [CenterDiscountController::class, 'couponsIndex'])
            ->name('coupons.index');
        Route::post('/{discount}/coupons', [CenterDiscountController::class, 'couponsStore'])
            ->name('coupons.store');
        Route::delete('/{discount}/coupons/{coupon}', [CenterDiscountController::class, 'couponsDestroy'])
            ->name('coupons.destroy');
        Route::patch('/{discount}/coupons/{coupon}/status', [CenterDiscountController::class, 'couponsUpdateStatus'])
            ->name('coupons.status');

        Route::post('/{discount}/discountables', [CenterDiscountController::class, 'discountablesAttach'])
            ->name('discountables.attach');
        Route::delete('/{discount}/discountables/{discountableType}/{discountableId}', [CenterDiscountController::class, 'discountablesDetach'])
            ->where('discountableType', '.*')
            ->name('discountables.detach');
    });
