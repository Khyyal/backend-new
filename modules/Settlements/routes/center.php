<?php

use Illuminate\Support\Facades\Route;
use Modules\Settlements\Http\Controllers\Center\PayoutController;

Route::prefix('centers')
    ->name('centers.')
    ->middleware(['auth:center_user', 'center.scope'])
    ->group(function (): void {
        Route::get('/{center}/balance', [PayoutController::class, 'balance'])
            ->name('balance');

        Route::put('/{center}/payout-account', [PayoutController::class, 'updateAccount'])
            ->name('payout-account.update');

        Route::get('/{center}/payouts', [PayoutController::class, 'index'])
            ->name('payouts.index');

        Route::get('/{center}/payouts/{payout}', [PayoutController::class, 'show'])
            ->whereNumber('payout')
            ->name('payouts.show');
    });
