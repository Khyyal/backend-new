<?php

namespace Modules\Settlements\Policies;

use Modules\Settlements\Models\CenterPayout;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

/**
 * Admin access to payouts, center ledgers and purchase financials. Centers
 * see their own payouts through CenterPolicy::viewPayouts.
 */
class CenterPayoutPolicy
{
    /**
     * Inactive system users are denied everything.
     */
    public function before(SystemUser $user): ?bool
    {
        return $user->isActive() ? null : false;
    }

    public function viewAny(SystemUser $user): bool
    {
        return $this->manages($user);
    }

    public function export(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ExportCenterLedgers);
    }

    public function view(SystemUser $user, CenterPayout $payout): bool
    {
        return $this->manages($user);
    }

    public function update(SystemUser $user, CenterPayout $payout): bool
    {
        return $this->manages($user);
    }

    private function manages(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ManagePayouts);
    }
}
