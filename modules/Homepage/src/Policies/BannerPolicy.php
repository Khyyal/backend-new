<?php

namespace Modules\Homepage\Policies;

use Modules\Homepage\Models\Banner;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

class BannerPolicy
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

    public function view(SystemUser $user, Banner $model): bool
    {
        return $this->manages($user);
    }

    public function create(SystemUser $user): bool
    {
        return $this->manages($user);
    }

    public function update(SystemUser $user, Banner $model): bool
    {
        return $this->manages($user);
    }

    public function delete(SystemUser $user, Banner $model): bool
    {
        return $this->manages($user);
    }

    private function manages(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ManageHomepage);
    }
}
