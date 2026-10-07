<?php

use Illuminate\Support\Facades\Route;
use Modules\Services\Http\Controllers\Center\RecreationRidingController;

Route::prefix('centers/{center}/recreation-ridings')
    ->name('centers.recreation-ridings.')
    ->middleware(['auth:center_user', 'center.scope'])
    ->group(function (): void {
        Route::get('/', [RecreationRidingController::class, 'index'])->name('index');
        Route::post('/', [RecreationRidingController::class, 'store'])->name('store');
        Route::get('/{recreationRiding}', [RecreationRidingController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{recreationRiding}', [RecreationRidingController::class, 'update'])->name('update');
        Route::delete('/{recreationRiding}', [RecreationRidingController::class, 'destroy'])->name('destroy');
    });
