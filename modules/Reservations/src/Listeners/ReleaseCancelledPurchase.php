<?php

namespace Modules\Reservations\Listeners;

use Modules\Purchase\Events\PurchaseCancelled;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Services\ReservationStateService;

/**
 * A cancelled purchase frees the places of its still-pending reservations and
 * voids its unpaid packages. Confirmed ones (cash on arrival) are left to the center.
 */
class ReleaseCancelledPurchase
{
    public function __construct(private ReservationStateService $reservationState) {}

    public function handle(PurchaseCancelled $event): void
    {
        foreach ($event->purchase->items()->get() as $item) {
            $purchasable = $item->purchasable;

            if ($purchasable instanceof Reservation && $purchasable->status === ReservationStatus::Pending) {
                $this->reservationState->cancel($purchasable, reason: 'purchase_cancelled');
            }

            if ($purchasable instanceof ClientPackage && $purchasable->status === ClientPackageStatus::Pending) {
                $purchasable->update(['status' => ClientPackageStatus::Cancelled]);
            }
        }
    }
}
