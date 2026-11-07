<?php

namespace Modules\Centers\Policies;

use Modules\Centers\Models\Tag;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

class TagPolicy
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
        return $user->hasPermissionTo(SystemPermission::ViewAnyTags);
    }

    public function view(SystemUser $user, Tag $tag): bool
    {
        return $user->hasPermissionTo(SystemPermission::ViewTag);
    }

    public function create(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::CreateTag);
    }

    public function update(SystemUser $user, Tag $tag): bool
    {
        return $user->hasPermissionTo(SystemPermission::UpdateTag);
    }

    public function delete(SystemUser $user, Tag $tag): bool
    {
        return $user->hasPermissionTo(SystemPermission::DeleteTag);
    }
}
