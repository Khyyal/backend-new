<?php

namespace Modules\Settlements\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Centers\Models\Center;
use Modules\Settlements\Enums\CenterPayoutStatus;
use Modules\Settlements\Models\CenterLedgerEntry;
use Modules\Settlements\Models\CenterPayout;

/**
 * Creates one pending payout per center for its unpaid ledger entries
 * available at `$cutoff`. Debits net against credits; a center whose net is
 * zero or negative, or that has no bank account, keeps its entries for the
 * next run. A center already paid out for this cutoff is skipped.
 */
class GenerateCenterPayouts
{
    /**
     * @return Collection<int, CenterPayout>
     */
    public function execute(Carbon $cutoff): Collection
    {
        $centerIds = CenterLedgerEntry::query()
            ->payableAt($cutoff)
            ->distinct()
            ->pluck('center_id');

        return $centerIds
            ->map(fn (int $centerId): ?CenterPayout => $this->generateFor($centerId, $cutoff))
            ->filter()
            ->values();
    }

    private function generateFor(int $centerId, Carbon $cutoff): ?CenterPayout
    {
        return DB::transaction(function () use ($centerId, $cutoff): ?CenterPayout {
            $center = Center::query()->whereKey($centerId)->lockForUpdate()->first();

            if ($center === null || CenterPayout::query()->where('center_id', $centerId)->where('period_end', $cutoff)->exists()) {
                return null;
            }

            $entries = CenterLedgerEntry::query()
                ->where('center_id', $centerId)
                ->payableAt($cutoff)
                ->lockForUpdate()
                ->get(['id', 'amount']);

            $amount = (int) $entries->sum('amount');

            if ($amount <= 0) {
                return null;
            }

            if (blank($center->payout_iban)) {
                Log::warning('Center payout skipped: no bank account.', ['center_id' => $centerId, 'amount' => $amount]);

                return null;
            }

            $payout = CenterPayout::create([
                'center_id' => $centerId,
                'period_end' => $cutoff,
                'amount' => $amount,
                'entries_count' => $entries->count(),
                'status' => CenterPayoutStatus::Pending,
                'iban' => $center->payout_iban,
                'account_name' => $center->payout_account_name,
                'bank_name' => $center->payout_bank_name,
            ]);

            CenterLedgerEntry::query()
                ->whereKey($entries->modelKeys())
                ->update(['payout_id' => $payout->id]);

            return $payout;
        });
    }
}
