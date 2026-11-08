<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\Admin\FeatureController as AdminFeatureController;
use Modules\Billing\Http\Controllers\Admin\LimitController as AdminLimitController;
use Modules\Billing\Http\Controllers\Admin\PlanController as AdminPlanController;
use Modules\Billing\Models\Plan;

Route::middleware(['api', 'auth:system_user'])->group(function (): void {

    // ---------- Admin Plans ----------
    Route::prefix('api/admin/plans')
        ->name('admin.plans.')
        ->group(function (): void {
            Route::get('/', [AdminPlanController::class, 'index'])
                ->middleware('can:viewAny,'.Plan::class)
                ->name('index');
            Route::post('/', [AdminPlanController::class, 'store'])
                ->middleware('can:create,'.Plan::class)
                ->name('store');
            Route::get('/{plan}', [AdminPlanController::class, 'show'])
                ->middleware('can:viewAny,'.Plan::class)
                ->name('show');
            Route::put('/{plan}', [AdminPlanController::class, 'update'])
                ->middleware('can:update,'.Plan::class)
                ->name('update');
            Route::delete('/{plan}', [AdminPlanController::class, 'destroy'])
                ->middleware('can:delete,'.Plan::class)
                ->name('destroy');
            Route::patch('/{plan}/status', [AdminPlanController::class, 'updateStatus'])
                ->middleware('can:update,'.Plan::class)
                ->name('status');

            Route::post('/{plan}/features', [AdminPlanController::class, 'attachFeatures'])
                ->middleware('can:update,'.Plan::class)
                ->name('features.attach');
            Route::delete('/{plan}/features/{feature}', [AdminPlanController::class, 'detachFeature'])
                ->middleware('can:update,'.Plan::class)
                ->name('features.detach');

            Route::post('/{plan}/limits', [AdminPlanController::class, 'attachLimits'])
                ->middleware('can:update,'.Plan::class)
                ->name('limits.attach');
            Route::delete('/{plan}/limits/{limit}', [AdminPlanController::class, 'detachLimit'])
                ->middleware('can:update,'.Plan::class)
                ->name('limits.detach');
        });

    // ---------- Admin Features ----------
    Route::prefix('api/admin/features')
        ->name('admin.features.')
        ->group(function (): void {
            Route::get('/', [AdminFeatureController::class, 'index'])
                ->middleware('can:viewAny,'.Plan::class)
                ->name('index');
            Route::post('/', [AdminFeatureController::class, 'store'])
                ->middleware('can:manageCatalog,'.Plan::class)
                ->name('store');
            Route::get('/{feature}', [AdminFeatureController::class, 'show'])
                ->middleware('can:viewAny,'.Plan::class)
                ->name('show');
            Route::put('/{feature}', [AdminFeatureController::class, 'update'])
                ->middleware('can:manageCatalog,'.Plan::class)
                ->name('update');
            Route::delete('/{feature}', [AdminFeatureController::class, 'destroy'])
                ->middleware('can:manageCatalog,'.Plan::class)
                ->name('destroy');
        });

    // ---------- Admin Limits ----------
    Route::prefix('api/admin/limits')
        ->name('admin.limits.')
        ->group(function (): void {
            Route::get('/', [AdminLimitController::class, 'index'])
                ->middleware('can:viewAny,'.Plan::class)
                ->name('index');
            Route::post('/', [AdminLimitController::class, 'store'])
                ->middleware('can:manageCatalog,'.Plan::class)
                ->name('store');
            Route::get('/{limit}', [AdminLimitController::class, 'show'])
                ->middleware('can:viewAny,'.Plan::class)
                ->name('show');
            Route::put('/{limit}', [AdminLimitController::class, 'update'])
                ->middleware('can:manageCatalog,'.Plan::class)
                ->name('update');
            Route::delete('/{limit}', [AdminLimitController::class, 'destroy'])
                ->middleware('can:manageCatalog,'.Plan::class)
                ->name('destroy');
        });
});
