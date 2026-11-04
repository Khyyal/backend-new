<?php

namespace Modules\Purchase\Services;

use Modules\Centers\Models\Center;
use Modules\Purchase\Enums\CenterPayoutStatus;
use Modules\Purchase\Models\CenterLedgerEntry;
use Modules\Purchase\Models\CenterPayout;

/**
 * Where a center's money stands: `available` goes into the next payout (it
 * may be negative and carry forward), `on_hold` is still inside the hold
 * period, `in_pending_payouts` awaits transfer and `paid_out` was transferred.
 */
class CenterBalance
{
    /**
     * @return array{available: int, on_hold: int, in_pending_payouts: int, paid_out: int}
     */
    public function for(Center $center): array
    {
        $unpaid = CenterLedgerEntry::query()
            ->where('center_id', $center->getKey())
            ->whereNull('payout_id')
            ->selectRaw('coalesce(sum(case when available_at <= ? then amount else 0 end), 0) as available', [now()])
            ->selectRaw('coalesce(sum(case when available_at > ? then amount else 0 end), 0) as on_hold', [now()])
            ->first();

        $payouts = CenterPayout::query()
            ->where('center_id', $center->getKey())
            ->selectRaw('coalesce(sum(case when status = ? then amount else 0 end), 0) as pending', [CenterPayoutStatus::Pending->value])
            ->selectRaw('coalesce(sum(case when status = ? then amount else 0 end), 0) as paid', [CenterPayoutStatus::Paid->value])
            ->first();

        return [
            'available' => (int) $unpaid->available,
            'on_hold' => (int) $unpaid->on_hold,
            'in_pending_payouts' => (int) $payouts->pending,
            'paid_out' => (int) $payouts->paid,
        ];
    }
}
