<?php

namespace Modules\Support\Policies;

use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\City;
use Modules\Support\Models\SystemUser;

class CityPolicy
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
        return $user->hasPermissionTo(SystemPermission::ViewAnyCities);
    }

    public function view(SystemUser $user, City $city): bool
    {
        return $user->hasPermissionTo(SystemPermission::ViewCity);
    }

    public function create(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::CreateCity);
    }

    public function update(SystemUser $user, City $city): bool
    {
        return $user->hasPermissionTo(SystemPermission::UpdateCity);
    }

    public function delete(SystemUser $user, City $city): bool
    {
        return $user->hasPermissionTo(SystemPermission::DeleteCity);
    }
}
