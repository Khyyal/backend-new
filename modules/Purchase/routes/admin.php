<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchase\Http\Controllers\Admin\CenterLedgerController;
use Modules\Purchase\Http\Controllers\Admin\CenterPayoutController;
use Modules\Purchase\Http\Controllers\Admin\FeeRuleController;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        Route::apiResource('fee-rules', FeeRuleController::class);

        Route::get('payouts', [CenterPayoutController::class, 'index'])->name('payouts.index');
        Route::get('payouts/{payout}', [CenterPayoutController::class, 'show'])->name('payouts.show');
        Route::post('payouts/{payout}/mark-paid', [CenterPayoutController::class, 'markPaid'])->name('payouts.mark-paid');
        Route::post('payouts/{payout}/cancel', [CenterPayoutController::class, 'cancel'])->name('payouts.cancel');

        Route::get('centers/{center}/ledger', [CenterLedgerController::class, 'index'])->name('centers.ledger');
        Route::get('purchases/{purchase}/financials', [CenterLedgerController::class, 'financials'])->name('purchases.financials');
    });
