<?php

use Illuminate\Support\Facades\Route;
use Modules\Services\Http\Controllers\Center\CenterPeriodController;
use Modules\Services\Http\Controllers\Center\EventController;
use Modules\Services\Http\Controllers\Center\HorseCareController;
use Modules\Services\Http\Controllers\Center\HorseServiceController;
use Modules\Services\Http\Controllers\Center\MembershipController;
use Modules\Services\Http\Controllers\Center\RecreationRidingController;
use Modules\Services\Http\Controllers\Center\ResortController;
use Modules\Services\Http\Controllers\Center\ServiceBlockController;
use Modules\Services\Http\Controllers\Center\ServiceListController;
use Modules\Services\Http\Controllers\Center\ServiceTypeCountController;
use Modules\Services\Http\Controllers\Center\ServiceTypeTermController;
use Modules\Services\Http\Controllers\Center\TrainerController;
use Modules\Services\Http\Controllers\Center\TrainingServiceController;
use Modules\Services\Http\Controllers\Center\VisitController;

/*
 * System-user access to a center's services. Reuses the center portal
 * controllers; authorization goes through CenterPolicy::manageServices.
 */
Route::prefix('admin/centers/{center}')
    ->name('admin.centers.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        $resources = [
            'recreation-ridings' => [RecreationRidingController::class, 'recreationRiding'],
            'visits' => [VisitController::class, 'visit'],
            'events' => [EventController::class, 'event'],
            'resorts' => [ResortController::class, 'resort'],
            'horse-cares' => [HorseCareController::class, 'horseCare'],
            'horse-services' => [HorseServiceController::class, 'horseService'],
            'training-services' => [TrainingServiceController::class, 'trainingService'],
            'memberships' => [MembershipController::class, 'membership'],
            'trainers' => [TrainerController::class, 'trainer'],
            'service-blocks' => [ServiceBlockController::class, 'serviceBlock'],
        ];

        Route::get('trainers/list', [TrainerController::class, 'list'])->name('trainers.list');
        Route::get('services/by-type', [ServiceListController::class, 'byType'])->name('services.by-type');

        foreach ($resources as $uri => [$controller, $parameter]) {
            Route::apiResource($uri, $controller)->parameters([$uri => $parameter]);
        }

        Route::prefix('periods')
            ->name('periods.')
            ->group(function (): void {
                Route::get('/', [CenterPeriodController::class, 'show'])->name('show');
                Route::match(['put', 'patch'], '/', [CenterPeriodController::class, 'update'])->name('update');
            });

        Route::prefix('service-types')
            ->name('service-types.')
            ->group(function (): void {
                Route::get('/', [ServiceTypeTermController::class, 'index'])->name('index');
                Route::get('/counts', [ServiceTypeCountController::class, 'index'])->name('counts');
                Route::put('/{type}/terms', [ServiceTypeTermController::class, 'updateTerms'])->name('terms.update');
            });
    });
