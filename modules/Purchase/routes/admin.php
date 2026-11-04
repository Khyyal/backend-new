<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchase\Http\Controllers\Admin\FeeRuleController;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        Route::apiResource('fee-rules', FeeRuleController::class);
    });
