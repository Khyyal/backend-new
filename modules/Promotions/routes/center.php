<?php

use Illuminate\Support\Facades\Route;
use Modules\Promotions\Http\Controllers\Center\CouponController;
use Modules\Promotions\Http\Controllers\Center\PromotionController;

Route::prefix('centers/{center}/promotions')
    ->name('centers.promotions.')
    ->middleware(['auth:center_user', 'center.scope'])
    ->scopeBindings()
    ->group(function (): void {
        Route::get('/', [PromotionController::class, 'index'])->name('index');
        Route::post('/', [PromotionController::class, 'store'])->name('store');
        Route::get('/{promotion}', [PromotionController::class, 'show'])->name('show');
        Route::put('/{promotion}', [PromotionController::class, 'update'])->name('update');
        Route::delete('/{promotion}', [PromotionController::class, 'destroy'])->name('destroy');
        Route::post('/{promotion}/activate', [PromotionController::class, 'activate'])->name('activate');
        Route::post('/{promotion}/pause', [PromotionController::class, 'pause'])->name('pause');
        Route::post('/{promotion}/archive', [PromotionController::class, 'archive'])->name('archive');
        Route::put('/{promotion}/targets', [PromotionController::class, 'syncTargets'])->name('targets.sync');
        Route::put('/{promotion}/schedules', [PromotionController::class, 'syncSchedules'])->name('schedules.sync');

        Route::get('/{promotion}/coupons', [CouponController::class, 'index'])->name('coupons.index');
        Route::post('/{promotion}/coupons', [CouponController::class, 'store'])->name('coupons.store');
        Route::post('/{promotion}/coupons/generate', [CouponController::class, 'generate'])->name('coupons.generate');
        Route::patch('/{promotion}/coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
        Route::get('/{promotion}/redemptions', [CouponController::class, 'redemptions'])->name('redemptions.index');
    });
