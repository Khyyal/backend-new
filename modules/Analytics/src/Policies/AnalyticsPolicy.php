<?php

namespace Modules\Analytics\Policies;

use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

/**
 * Admin access to the platform analytics dashboards and exports.
 */
class AnalyticsPolicy
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
        return $user->hasPermissionTo(SystemPermission::ViewAnalytics);
    }
}
