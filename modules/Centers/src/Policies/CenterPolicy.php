<?php

namespace Modules\Centers\Policies;

use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

/**
 * Serves two audiences: center users (scoped by their assignment to the
 * center) and system users (scoped by their role permissions). Abilities
 * with no center-user meaning deny center users.
 */
class CenterPolicy
{
    public function viewAny(User|SystemUser $user): bool
    {
        return $this->systemUserCan($user, SystemPermission::ViewAnyCenters);
    }

    public function view(User|SystemUser $user, Center $center): bool
    {
        return $this->systemUserCan($user, SystemPermission::ViewCenter);
    }

    public function create(User|SystemUser $user): bool
    {
        return $this->systemUserCan($user, SystemPermission::CreateCenter);
    }

    /**
     * Center users: only the center's primary user. System users: by permission.
     */
    public function update(User|SystemUser $user, Center $center): bool
    {
        if ($user instanceof SystemUser) {
            return $this->systemUserCan($user, SystemPermission::UpdateCenter);
        }

        return $user->centers()
            ->whereKey($center->getKey())
            ->wherePivot('is_primary', true)
            ->exists();
    }

    public function delete(User|SystemUser $user, Center $center): bool
    {
        return $this->systemUserCan($user, SystemPermission::DeleteCenter);
    }

    /**
     * Center users: any user assigned to the center. System users: by permission.
     */
    public function manageServices(User|SystemUser $user, Center $center): bool
    {
        return $this->canAccessCenter($user, $center, SystemPermission::ManageCenterServices);
    }

    public function viewRatings(User|SystemUser $user, Center $center): bool
    {
        return $this->canAccessCenter($user, $center, SystemPermission::ViewCenterRatings);
    }

    public function manageBlockedClients(User|SystemUser $user, Center $center): bool
    {
        return $this->canAccessCenter($user, $center, SystemPermission::ManageCenterBlockedClients);
    }

    /**
     * Center users: any user assigned to the center. System users: by permission.
     */
    public function manageReservations(User|SystemUser $user, Center $center): bool
    {
        return $this->canAccessCenter($user, $center, SystemPermission::ManageCenterReservations);
    }

    /**
     * Center users: any user assigned to the center. System users: denied,
     * discounts are managed by the center only.
     */
    public function manageDiscounts(User|SystemUser $user, Center $center): bool
    {
        return $user instanceof User
            && $user->centers()->whereKey($center->getKey())->exists();
    }

    private function canAccessCenter(User|SystemUser $user, Center $center, SystemPermission $permission): bool
    {
        if ($user instanceof SystemUser) {
            return $this->systemUserCan($user, $permission);
        }

        return $user->centers()->whereKey($center->getKey())->exists();
    }

    private function systemUserCan(User|SystemUser $user, SystemPermission $permission): bool
    {
        return $user instanceof SystemUser
            && $user->isActive()
            && $user->hasPermissionTo($permission);
    }
}
