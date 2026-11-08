<?php

namespace Modules\Billing\Policies;

use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

class SubscriptionPolicy
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
        return $user->hasPermissionTo(SystemPermission::ViewAnySubscriptions);
    }

    public function change(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ChangeSubscriptions);
    }
}
