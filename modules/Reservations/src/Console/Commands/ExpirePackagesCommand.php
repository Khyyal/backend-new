<?php

namespace Modules\Reservations\Console\Commands;

use Illuminate\Console\Command;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Models\ClientPackage;

/**
 * Marks active packages past their validity as expired. Expired packages
 * already refuse bookings; this keeps their status honest.
 */
class ExpirePackagesCommand extends Command
{
    protected $signature = 'reservations:expire-packages';

    protected $description = 'Mark active client packages past their expiry date as expired';

    public function handle(): int
    {
        $expired = ClientPackage::query()
            ->where('status', ClientPackageStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => ClientPackageStatus::Expired, 'updated_at' => now()]);

        $this->info("Expired {$expired} package(s).");

        return self::SUCCESS;
    }
}
