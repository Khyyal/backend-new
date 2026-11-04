<?php

namespace Modules\Purchase\Services;

use Modules\Centers\Models\Center;
use Modules\Purchase\Enums\CenterLedgerEntryType;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;

/**
 * How a purchase splits its money between the center, the platform and the
 * payment provider, read from its payments and ledger entries.
 *
 * `center_liability` is what the platform owes the center for the purchase
 * (negative when the center collected the money and owes the fee), and
 * `platform_net_revenue` what the platform keeps after paying the center,
 * the provider and any discount it funded.
 */
class PurchaseFinancials
{
    /**
     * @return array{gross_paid: int, refunded: int, provider_fee: int, platform_fee: int, platform_subsidy: int, center_liability: int, platform_net_revenue: int}
     */
    public function for(Purchase $purchase): array
    {
        $purchase->loadMissing(['payments', 'ledgerEntries']);

        $paid = $purchase->payments->filter(
            fn (Payment $payment): bool => in_array($payment->status, [PaymentStatus::Succeeded, PaymentStatus::Refunded], true)
        );

        $grossPaid = (int) $paid->sum('amount');
        $refunded = (int) $paid->sum('refunded_amount');
        $providerFee = (int) $paid->sum('provider_fee_amount');

        if ($purchase->merchant_type !== (new Center)->getMorphClass()) {
            return [
                'gross_paid' => $grossPaid,
                'refunded' => $refunded,
                'provider_fee' => $providerFee,
                'platform_fee' => 0,
                'platform_subsidy' => 0,
                'center_liability' => 0,
                'platform_net_revenue' => $grossPaid - $refunded - $providerFee,
            ];
        }

        $entries = $purchase->ledgerEntries;
        $platformFee = -(int) $entries->whereIn('type', CenterLedgerEntryType::feeTypes())->sum('amount');
        $subsidy = (int) $entries->whereIn('type', CenterLedgerEntryType::subsidyTypes())->sum('amount');

        return [
            'gross_paid' => $grossPaid,
            'refunded' => $refunded,
            'provider_fee' => $providerFee,
            'platform_fee' => $platformFee,
            'platform_subsidy' => $subsidy,
            'center_liability' => (int) $entries->sum('amount'),
            'platform_net_revenue' => $platformFee - $providerFee - $subsidy,
        ];
    }
}
