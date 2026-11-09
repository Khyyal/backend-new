<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\Admin\ActivityLogController;
use Modules\Support\Http\Controllers\Admin\AuthController;
use Modules\Support\Http\Controllers\Admin\CityController;
use Modules\Support\Http\Controllers\Admin\LegalDocumentController;
use Modules\Support\Http\Controllers\Admin\SystemPermissionController;
use Modules\Support\Http\Controllers\Admin\SystemRoleController;
use Modules\Support\Http\Controllers\Admin\SystemUserController;

Route::prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::prefix('auth')
            ->name('auth.')
            ->group(function () {
                Route::post('/login', [AuthController::class, 'login'])
                    ->middleware('throttle:10,1')
                    ->name('login');

                Route::get('/me', [AuthController::class, 'me'])
                    ->middleware('auth:system_user')
                    ->name('me');
            });

        Route::middleware('auth:system_user')
            ->apiResource('cities', CityController::class);

        Route::middleware('auth:system_user')->group(function () {
            Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
            Route::get('activity-logs/{activity}', [ActivityLogController::class, 'show'])
                ->whereNumber('activity')
                ->name('activity-logs.show');

            Route::apiResource('system-users', SystemUserController::class)
                ->parameters(['system-users' => 'systemUser'])
                ->withTrashed(['show']);
            Route::post('system-users/{systemUser}/restore', [SystemUserController::class, 'restore'])
                ->withTrashed()
                ->name('system-users.restore');

            Route::apiResource('system-roles', SystemRoleController::class)
                ->parameters(['system-roles' => 'systemRole']);
            Route::get('system-permissions', SystemPermissionController::class)->name('system-permissions.index');

            Route::get('legal-documents', [LegalDocumentController::class, 'index'])->name('legal-documents.index');
            Route::get('legal-documents/{audience}/{type}', [LegalDocumentController::class, 'show'])->name('legal-documents.show');
            Route::put('legal-documents/{audience}/{type}', [LegalDocumentController::class, 'update'])->name('legal-documents.update');
        });
    });
