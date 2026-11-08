<?php

use Illuminate\Support\Facades\Route;
use Modules\Exports\Http\Controllers\Admin\ExportController;

Route::prefix('admin/exports')
    ->name('admin.exports.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        Route::get('/', [ExportController::class, 'index'])->name('index');
        Route::get('/{export}', [ExportController::class, 'show'])->name('show');
        Route::get('/{export}/download', [ExportController::class, 'download'])->name('download');
    });
