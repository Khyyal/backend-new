<?php

use Illuminate\Support\Facades\Route;
use Modules\Analytics\Http\Controllers\Admin\AnalyticsController;

Route::prefix('admin/analytics')
    ->name('admin.analytics.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        Route::get('/overview', [AnalyticsController::class, 'overview'])->name('overview');
        Route::get('/revenue', [AnalyticsController::class, 'revenue'])->name('revenue');
        Route::get('/bookings', [AnalyticsController::class, 'bookings'])->name('bookings');
        Route::get('/payments', [AnalyticsController::class, 'payments'])->name('payments');
        Route::get('/clients', [AnalyticsController::class, 'clients'])->name('clients');
        Route::get('/centers', [AnalyticsController::class, 'centers'])->name('centers');
        Route::get('/promotions', [AnalyticsController::class, 'promotions'])->name('promotions');
        Route::get('/subscriptions', [AnalyticsController::class, 'subscriptions'])->name('subscriptions');
        Route::get('/engagement', [AnalyticsController::class, 'engagement'])->name('engagement');
        Route::get('/export', [AnalyticsController::class, 'export'])->name('export');
    });
