<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\Admin\FeatureController as AdminFeatureController;
use Modules\Billing\Http\Controllers\Admin\LimitController as AdminLimitController;
use Modules\Billing\Http\Controllers\Admin\PlanController as AdminPlanController;

Route::middleware(['api', 'auth:sanctum'])->group(function (): void {

    // ---------- Admin Plans ----------
    Route::prefix('api/admin/plans')
        ->name('admin.plans.')
        ->group(function (): void {
            Route::get('/', [AdminPlanController::class, 'index'])
                ->name('index');
            Route::post('/', [AdminPlanController::class, 'store'])
                ->name('store');
            Route::get('/{plan}', [AdminPlanController::class, 'show'])
                ->name('show');
            Route::put('/{plan}', [AdminPlanController::class, 'update'])
                ->name('update');
            Route::delete('/{plan}', [AdminPlanController::class, 'destroy'])
                ->name('destroy');
            Route::patch('/{plan}/status', [AdminPlanController::class, 'updateStatus'])
                ->name('status');

            Route::post('/{plan}/features', [AdminPlanController::class, 'attachFeatures'])
                ->name('features.attach');
            Route::delete('/{plan}/features/{feature}', [AdminPlanController::class, 'detachFeature'])
                ->name('features.detach');

            Route::post('/{plan}/limits', [AdminPlanController::class, 'attachLimits'])
                ->name('limits.attach');
            Route::delete('/{plan}/limits/{limit}', [AdminPlanController::class, 'detachLimit'])
                ->name('limits.detach');
        });

    // ---------- Admin Features ----------
    Route::prefix('api/admin/features')
        ->name('admin.features.')
        ->group(function (): void {
            Route::get('/', [AdminFeatureController::class, 'index'])
                ->name('index');
            Route::post('/', [AdminFeatureController::class, 'store'])
                ->name('store');
            Route::get('/{feature}', [AdminFeatureController::class, 'show'])
                ->name('show');
            Route::put('/{feature}', [AdminFeatureController::class, 'update'])
                ->name('update');
            Route::delete('/{feature}', [AdminFeatureController::class, 'destroy'])
                ->name('destroy');
        });

    // ---------- Admin Limits ----------
    Route::prefix('api/admin/limits')
        ->name('admin.limits.')
        ->group(function (): void {
            Route::get('/', [AdminLimitController::class, 'index'])
                ->name('index');
            Route::post('/', [AdminLimitController::class, 'store'])
                ->name('store');
            Route::get('/{limit}', [AdminLimitController::class, 'show'])
                ->name('show');
            Route::put('/{limit}', [AdminLimitController::class, 'update'])
                ->name('update');
            Route::delete('/{limit}', [AdminLimitController::class, 'destroy'])
                ->name('destroy');
        });
});
