<?php

use Illuminate\Support\Facades\Route;
use Modules\Settlements\Http\Controllers\Admin\CenterLedgerController;
use Modules\Settlements\Http\Controllers\Admin\CenterPayoutController;
use Modules\Settlements\Http\Controllers\Admin\PayoutInvoiceController;
use Modules\Settlements\Http\Controllers\Admin\PayoutInvoiceTemplateController;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        Route::get('payouts', [CenterPayoutController::class, 'index'])->name('payouts.index');
        Route::get('payout-invoice-template', [PayoutInvoiceTemplateController::class, 'show'])->name('payout-invoice-template.show');
        Route::put('payout-invoice-template', [PayoutInvoiceTemplateController::class, 'update'])->name('payout-invoice-template.update');
        Route::post('payout-invoice-template/preview', [PayoutInvoiceTemplateController::class, 'preview'])->name('payout-invoice-template.preview');
        Route::get('payouts/{payout}', [CenterPayoutController::class, 'show'])->name('payouts.show');
        Route::get('payouts/{payout}/invoice', [PayoutInvoiceController::class, 'show'])->name('payouts.invoice');
        Route::post('payouts/{payout}/mark-paid', [CenterPayoutController::class, 'markPaid'])->name('payouts.mark-paid');
        Route::post('payouts/{payout}/cancel', [CenterPayoutController::class, 'cancel'])->name('payouts.cancel');

        Route::get('centers/{center}/ledger', [CenterLedgerController::class, 'index'])->name('centers.ledger');
        Route::post('centers/{center}/ledger/export', [CenterLedgerController::class, 'export'])->name('centers.ledger.export');
        Route::get('purchases/{purchase}/financials', [CenterLedgerController::class, 'financials'])->name('purchases.financials');
    });
