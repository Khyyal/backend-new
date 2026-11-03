<?php

use Illuminate\Support\Facades\Route;
use Modules\Promotions\Http\Controllers\Admin\CampaignController;
use Modules\Promotions\Http\Controllers\Admin\CouponController;
use Modules\Promotions\Http\Controllers\Admin\PromotionController;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        Route::apiResource('campaigns', CampaignController::class);
        Route::apiResource('promotions', PromotionController::class);

        Route::prefix('promotions/{promotion}')
            ->name('promotions.')
            ->group(function (): void {
                Route::post('/activate', [PromotionController::class, 'activate'])->name('activate');
                Route::post('/pause', [PromotionController::class, 'pause'])->name('pause');
                Route::post('/archive', [PromotionController::class, 'archive'])->name('archive');
                Route::put('/targets', [PromotionController::class, 'syncTargets'])->name('targets.sync');
                Route::put('/schedules', [PromotionController::class, 'syncSchedules'])->name('schedules.sync');
                Route::get('/coupons', [CouponController::class, 'index'])->name('coupons.index');
                Route::post('/coupons', [CouponController::class, 'store'])->name('coupons.store');
                Route::post('/coupons/generate', [CouponController::class, 'generate'])->name('coupons.generate');
                Route::get('/redemptions', [CouponController::class, 'redemptions'])->name('redemptions.index');
            });

        Route::prefix('coupons/{coupon}')
            ->name('coupons.')
            ->group(function (): void {
                Route::patch('/', [CouponController::class, 'update'])->name('update');
                Route::post('/assignments', [CouponController::class, 'assign'])->name('assignments.store');
                Route::delete('/assignments', [CouponController::class, 'unassign'])->name('assignments.destroy');
            });
    });
