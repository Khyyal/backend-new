<?php

namespace Modules\Support\Policies;

use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

class ActivityLogPolicy
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
        return $user->hasPermissionTo(SystemPermission::ViewAnyActivityLogs);
    }
}
