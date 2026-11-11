<?php

namespace Modules\Reservations\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\ClientPackage;

/**
 * Gives a client package extra session credits. An exhausted package works
 * again (or stays expired when its validity has passed).
 */
class AddPackageSessions
{
    /**
     * @throws ReservationNotAllowedException
     */
    public function execute(ClientPackage $clientPackage, int $sessions): ClientPackage
    {
        return DB::transaction(function () use ($clientPackage, $sessions): ClientPackage {
            $package = ClientPackage::query()->whereKey($clientPackage->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($package->status, [ClientPackageStatus::Pending, ClientPackageStatus::Cancelled], true)) {
                throw ReservationNotAllowedException::because('package_not_adjustable');
            }

            $package->total_sessions += $sessions;
            $package->remaining_sessions += $sessions;

            if ($package->status === ClientPackageStatus::Exhausted) {
                $package->status = $package->isExpired() ? ClientPackageStatus::Expired : ClientPackageStatus::Active;
            }

            $package->save();

            return $package;
        });
    }
}
