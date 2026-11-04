<?php

use Illuminate\Support\Facades\Route;
use Modules\Homepage\Http\Controllers\Admin\AnalyticsController;
use Modules\Homepage\Http\Controllers\Admin\BannerController;
use Modules\Homepage\Http\Controllers\Admin\HomeSectionController;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        Route::put('/home-sections/reorder', [HomeSectionController::class, 'reorder'])->name('home-sections.reorder');
        Route::apiResource('home-sections', HomeSectionController::class)->parameters(['home-sections' => 'section']);
        Route::put('/home-sections/{section}/items', [HomeSectionController::class, 'syncItems'])->name('home-sections.items.sync');
        Route::get('/home-sections/{section}/item-candidates', [HomeSectionController::class, 'itemCandidates'])->name('home-sections.item-candidates');

        Route::apiResource('banners', BannerController::class);

        Route::get('/analytics/home-content', [AnalyticsController::class, 'homeContent'])->name('analytics.home-content');
    });
