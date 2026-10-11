<?php

namespace Modules\Support\Policies;

use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

class SystemUserPolicy
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
        return $user->hasPermissionTo(SystemPermission::ViewAnySystemUsers);
    }

    public function view(SystemUser $user, SystemUser $target): bool
    {
        return $user->is($target) || $user->hasPermissionTo(SystemPermission::ViewSystemUser);
    }

    public function create(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::CreateSystemUser);
    }

    public function update(SystemUser $user, SystemUser $target): bool
    {
        return $user->is($target) || $user->hasPermissionTo(SystemPermission::UpdateSystemUser);
    }

    /**
     * Nobody may delete themselves.
     */
    public function delete(SystemUser $user, SystemUser $target): bool
    {
        return ! $user->is($target)
            && $user->hasPermissionTo(SystemPermission::DeleteSystemUser);
    }

    public function restore(SystemUser $user, SystemUser $target): bool
    {
        return $user->hasPermissionTo(SystemPermission::RestoreSystemUser);
    }

    public function forceDelete(SystemUser $user, SystemUser $target): bool
    {
        return ! $user->is($target)
            && $user->hasPermissionTo(SystemPermission::ForceDeleteSystemUser);
    }

    public function assignRoles(SystemUser $user, SystemUser $target): bool
    {
        return ! $user->is($target)
            && $user->hasPermissionTo(SystemPermission::AssignSystemUserRoles);
    }
}
