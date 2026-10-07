<?php

use Illuminate\Support\Facades\Route;
use Modules\Services\Http\Controllers\Center\EventController;
use Modules\Services\Http\Controllers\Center\RecreationRidingController;
use Modules\Services\Http\Controllers\Center\ResortController;
use Modules\Services\Http\Controllers\Center\ServiceTypeTermController;
use Modules\Services\Http\Controllers\Center\VisitController;

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

Route::prefix('centers/{center}/visits')
    ->name('centers.visits.')
    ->middleware(['auth:center_user', 'center.scope'])
    ->group(function (): void {
        Route::get('/', [VisitController::class, 'index'])->name('index');
        Route::post('/', [VisitController::class, 'store'])->name('store');
        Route::get('/{visit}', [VisitController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{visit}', [VisitController::class, 'update'])->name('update');
        Route::delete('/{visit}', [VisitController::class, 'destroy'])->name('destroy');
    });

Route::prefix('centers/{center}/events')
    ->name('centers.events.')
    ->middleware(['auth:center_user', 'center.scope'])
    ->group(function (): void {
        Route::get('/', [EventController::class, 'index'])->name('index');
        Route::post('/', [EventController::class, 'store'])->name('store');
        Route::get('/{event}', [EventController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{event}', [EventController::class, 'update'])->name('update');
        Route::delete('/{event}', [EventController::class, 'destroy'])->name('destroy');
    });

Route::prefix('centers/{center}/resorts')
    ->name('centers.resorts.')
    ->middleware(['auth:center_user', 'center.scope'])
    ->group(function (): void {
        Route::get('/', [ResortController::class, 'index'])->name('index');
        Route::post('/', [ResortController::class, 'store'])->name('store');
        Route::get('/{resort}', [ResortController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{resort}', [ResortController::class, 'update'])->name('update');
        Route::delete('/{resort}', [ResortController::class, 'destroy'])->name('destroy');
    });

Route::prefix('centers/{center}/service-types')
    ->name('centers.service-types.')
    ->middleware(['auth:center_user', 'center.scope'])
    ->group(function (): void {
        Route::get('/', [ServiceTypeTermController::class, 'index'])->name('index');
        Route::put('/{type}/terms', [ServiceTypeTermController::class, 'updateTerms'])->name('terms.update');
    });
