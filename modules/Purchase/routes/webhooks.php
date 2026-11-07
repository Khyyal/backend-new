<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchase\Http\Controllers\PaymentVerificationController;
use Modules\Purchase\Http\Controllers\WebhookController;

Route::prefix('webhooks')
    ->name('webhooks.')
    ->middleware(['throttle:120,1'])
    ->group(function (): void {
        Route::post('/moyasar', [WebhookController::class, 'moyasar'])->name('moyasar');
        Route::post('/tamara', [WebhookController::class, 'tamara'])->name('tamara');
    });

Route::middleware(['set.locale.from.accept.language', 'auth:client'])
    ->prefix('clients/payments')
    ->name('client.payments.')
    ->group(function (): void {
        Route::post('/{payment}/verify', [PaymentVerificationController::class, 'client'])->name('verify');
    });

Route::middleware(['set.locale.from.accept.language', 'auth:center_user', 'center.scope'])
    ->prefix('centers/{center}/payments')
    ->name('centers.payments.')
    ->group(function (): void {
        Route::post('/{payment}/verify', [PaymentVerificationController::class, 'center'])->name('verify');
    });
