<?php

namespace Modules\Purchase\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Purchase\Enums\CenterPayoutStatus;
use Modules\Purchase\Events\CenterPayoutPaid;
use Modules\Purchase\Models\CenterLedgerEntry;
use Modules\Purchase\Models\CenterPayout;
use Modules\Support\Models\SystemUser;

/**
 * The only way a payout changes: pending -> paid once the bank transfer is
 * made, or pending -> cancelled, which releases its entries to the next run.
 */
class CenterPayoutStateService
{
    public function markPaid(CenterPayout $payout, string $reference, SystemUser $by): CenterPayout
    {
        return DB::transaction(function () use ($payout, $reference, $by): CenterPayout {
            $locked = $this->lockPending($payout);

            $locked->update([
                'status' => CenterPayoutStatus::Paid,
                'reference' => $reference,
                'paid_at' => now(),
                'paid_by' => $by->getKey(),
            ]);

            DB::afterCommit(static function () use ($locked): void {
                event(new CenterPayoutPaid($locked));
            });

            return $locked;
        });
    }

    public function cancel(CenterPayout $payout): CenterPayout
    {
        return DB::transaction(function () use ($payout): CenterPayout {
            $locked = $this->lockPending($payout);

            CenterLedgerEntry::query()
                ->where('payout_id', $locked->id)
                ->update(['payout_id' => null]);

            $locked->update([
                'status' => CenterPayoutStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            return $locked;
        });
    }

    private function lockPending(CenterPayout $payout): CenterPayout
    {
        $locked = CenterPayout::query()->whereKey($payout->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->status !== CenterPayoutStatus::Pending) {
            throw new InvalidArgumentException(sprintf(
                'Cannot change a payout with status "%s". Only pending payouts can be paid or cancelled.',
                $locked->status->value
            ));
        }

        return $locked;
    }
}
