<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\Admin\SubscriptionController;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        Route::get('/subscriptions', [SubscriptionController::class, 'index'])
            ->name('subscriptions.index');
        Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show'])
            ->whereNumber('subscription')
            ->name('subscriptions.show');

        Route::get('/centers/{center}/subscription', [SubscriptionController::class, 'current'])
            ->whereNumber('center')
            ->name('centers.subscription.show');
        Route::post('/centers/{center}/subscription', [SubscriptionController::class, 'change'])
            ->whereNumber('center')
            ->name('centers.subscription.change');
    });
