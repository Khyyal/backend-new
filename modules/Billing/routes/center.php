<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\Center\PlanController as CenterPlanController;
use Modules\Billing\Http\Controllers\Center\SubscriptionController as CenterSubscriptionController;

Route::middleware(['api', 'auth:center_user'])->group(function (): void {

    // ---------- Center Plans (catalog) ----------
    Route::prefix('api/center/plans')
        ->name('center.plans.')
        ->group(function (): void {
            Route::get('/', [CenterPlanController::class, 'index'])
                ->name('index');
            Route::get('/{plan}', [CenterPlanController::class, 'show'])
                ->name('show');
        });

    // ---------- Center Subscriptions ----------
    Route::prefix('api/center/subscriptions')
        ->name('center.subscriptions.')
        ->group(function (): void {
            Route::get('/', [CenterSubscriptionController::class, 'index'])
                ->name('index');
            Route::get('/current', [CenterSubscriptionController::class, 'current'])
                ->name('current');
            Route::post('/', [CenterSubscriptionController::class, 'store'])
                ->name('store');
            Route::delete('/{subscription}', [CenterSubscriptionController::class, 'destroy'])
                ->name('destroy');
        });
});
