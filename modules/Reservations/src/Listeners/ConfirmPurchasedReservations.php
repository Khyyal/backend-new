<?php

namespace Modules\Reservations\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Purchase\Events\PurchaseConfirmed;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Events\ClientPackageActivated;
use Modules\Reservations\Models\ClientMembership;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Services\ReservationStateService;

/**
 * A confirmed purchase confirms its pending reservations and activates its
 * packages and memberships; validity starts counting from activation. A
 * membership activates the packages it holds, each with its own validity.
 */
class ConfirmPurchasedReservations
{
    public function __construct(private ReservationStateService $reservationState) {}

    public function handle(PurchaseConfirmed $event): void
    {
        $items = $event->purchase->items()->get();

        foreach ($items as $item) {
            $purchasable = $item->purchasable;

            if ($purchasable instanceof Reservation && $purchasable->status === ReservationStatus::Pending) {
                $this->reservationState->confirm($purchasable);
            }

            if ($purchasable instanceof ClientPackage) {
                $this->activate($purchasable);
            }

            if ($purchasable instanceof ClientMembership) {
                $this->activateMembership($purchasable);
            }
        }
    }

    private function activateMembership(ClientMembership $clientMembership): void
    {
        DB::transaction(function () use ($clientMembership): void {
            $membership = ClientMembership::query()->whereKey($clientMembership->getKey())->lockForUpdate()->firstOrFail();

            if ($membership->status !== ClientMembershipStatus::Pending) {
                return;
            }

            $membership->status = ClientMembershipStatus::Active;
            $membership->expires_at = now()->addMonths($membership->duration);
            $membership->save();

            $membership->packages()->get()->each(fn (ClientPackage $package) => $this->activate($package));
        });
    }

    private function activate(ClientPackage $clientPackage): void
    {
        DB::transaction(function () use ($clientPackage): void {
            $package = ClientPackage::query()->whereKey($clientPackage->getKey())->lockForUpdate()->firstOrFail();

            if ($package->status !== ClientPackageStatus::Pending) {
                return;
            }

            $package->status = ClientPackageStatus::Active;
            $package->expires_at = now()->addMonths((int) ($package->servicePackage?->duration ?? 1));
            $package->save();

            DB::afterCommit(static function () use ($package): void {
                event(new ClientPackageActivated($package));
            });
        });
    }
}
