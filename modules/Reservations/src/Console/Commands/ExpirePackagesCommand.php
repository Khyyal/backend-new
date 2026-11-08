<?php

namespace Modules\Reservations\Console\Commands;

use Illuminate\Console\Command;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Models\ClientMembership;
use Modules\Reservations\Models\ClientPackage;

/**
 * Marks active packages and memberships past their validity as expired.
 * Expired ones already refuse bookings; this keeps their status honest.
 */
class ExpirePackagesCommand extends Command
{
    protected $signature = 'reservations:expire-packages';

    protected $description = 'Mark active client packages and memberships past their expiry date as expired';

    public function handle(): int
    {
        $expired = 0;

        // Saved one by one so each status change fires model events and is audited.
        ClientPackage::query()
            ->where('status', ClientPackageStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->lazyById()
            ->each(function (ClientPackage $package) use (&$expired): void {
                $package->update(['status' => ClientPackageStatus::Expired]);
                $expired++;
            });

        $expiredMemberships = 0;

        ClientMembership::query()
            ->where('status', ClientMembershipStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->lazyById()
            ->each(function (ClientMembership $membership) use (&$expiredMemberships): void {
                $membership->update(['status' => ClientMembershipStatus::Expired]);
                $expiredMemberships++;
            });

        $this->info("Expired {$expired} package(s) and {$expiredMemberships} membership(s).");

        return self::SUCCESS;
    }
}
