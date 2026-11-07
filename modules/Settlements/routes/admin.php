<?php

use Illuminate\Support\Facades\Route;
use Modules\Settlements\Http\Controllers\Admin\CenterLedgerController;
use Modules\Settlements\Http\Controllers\Admin\CenterPayoutController;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        Route::get('payouts', [CenterPayoutController::class, 'index'])->name('payouts.index');
        Route::get('payouts/{payout}', [CenterPayoutController::class, 'show'])->name('payouts.show');
        Route::post('payouts/{payout}/mark-paid', [CenterPayoutController::class, 'markPaid'])->name('payouts.mark-paid');
        Route::post('payouts/{payout}/cancel', [CenterPayoutController::class, 'cancel'])->name('payouts.cancel');

        Route::get('centers/{center}/ledger', [CenterLedgerController::class, 'index'])->name('centers.ledger');
        Route::get('purchases/{purchase}/financials', [CenterLedgerController::class, 'financials'])->name('purchases.financials');
    });
