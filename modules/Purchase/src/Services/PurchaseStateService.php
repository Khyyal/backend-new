<?php

namespace Modules\Purchase\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Events\PurchaseCancelled;
use Modules\Purchase\Events\PurchaseCompleted;
use Modules\Purchase\Events\PurchaseConfirmed;
use Modules\Purchase\Models\Purchase;

class PurchaseStateService
{
    public function confirm(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase): Purchase {
            $locked = Purchase::query()
                ->whereKey($purchase->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== PurchaseStatus::Pending) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Cannot confirm purchase with status "%s". Only pending purchases can be confirmed.',
                        $locked->status->value
                    )
                );
            }

            $locked->status = PurchaseStatus::Confirmed;
            $locked->save();

            DB::afterCommit(static function () use ($locked): void {
                event(new PurchaseConfirmed($locked));
            });

            return $locked;
        });
    }

    public function cancel(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase): Purchase {
            $locked = Purchase::query()
                ->whereKey($purchase->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $allowedFrom = [PurchaseStatus::Pending, PurchaseStatus::Confirmed];

            if (! in_array($locked->status, $allowedFrom, true)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Cannot cancel purchase with status "%s". Only pending or confirmed purchases can be cancelled.',
                        $locked->status->value
                    )
                );
            }

            $locked->status = PurchaseStatus::Cancelled;
            $locked->save();

            DB::afterCommit(static function () use ($locked): void {
                event(new PurchaseCancelled($locked));
            });

            return $locked;
        });
    }

    public function complete(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase): Purchase {
            $locked = Purchase::query()
                ->whereKey($purchase->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== PurchaseStatus::Confirmed) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Cannot complete purchase with status "%s". Only confirmed purchases can be completed.',
                        $locked->status->value
                    )
                );
            }

            $locked->status = PurchaseStatus::Completed;
            $locked->save();

            DB::afterCommit(static function () use ($locked): void {
                event(new PurchaseCompleted($locked));
            });

            return $locked;
        });
    }
}
