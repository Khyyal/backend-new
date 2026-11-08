<?php

namespace Modules\Support\Policies;

use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemRole;
use Modules\Support\Models\SystemUser;

class SystemRolePolicy
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
        return $user->hasPermissionTo(SystemPermission::ViewAnySystemRoles);
    }

    public function view(SystemUser $user, SystemRole $role): bool
    {
        return $user->hasPermissionTo(SystemPermission::ViewSystemRole);
    }

    public function create(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::CreateSystemRole);
    }

    /**
     * Protected roles are never edited.
     */
    public function update(SystemUser $user, SystemRole $role): bool
    {
        return ! $role->isProtected() && $user->hasPermissionTo(SystemPermission::UpdateSystemRole);
    }

    /**
     * Protected roles are never deleted.
     */
    public function delete(SystemUser $user, SystemRole $role): bool
    {
        return ! $role->isProtected() && $user->hasPermissionTo(SystemPermission::DeleteSystemRole);
    }
}
