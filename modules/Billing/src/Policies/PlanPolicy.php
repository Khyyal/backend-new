<?php

namespace Modules\Billing\Policies;

use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

class PlanPolicy
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
        return $user->hasPermissionTo(SystemPermission::ViewAnyPlans);
    }

    public function create(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::CreatePlan);
    }

    public function update(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::UpdatePlan);
    }

    public function delete(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::DeletePlan);
    }

    /**
     * Create, update and delete the reusable features and limits.
     */
    public function manageCatalog(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ManagePlanCatalog);
    }
}
