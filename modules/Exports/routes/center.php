<?php

use Illuminate\Support\Facades\Route;
use Modules\Exports\Http\Controllers\Center\ExportController;

Route::prefix('centers/{center}/exports')
    ->name('centers.exports.')
    ->middleware(['auth:center_user', 'center.scope'])
    ->group(function (): void {
        Route::get('/', [ExportController::class, 'index'])->name('index');
        Route::get('/{export}', [ExportController::class, 'show'])->name('show');
        Route::get('/{export}/download', [ExportController::class, 'download'])->name('download');
    });
