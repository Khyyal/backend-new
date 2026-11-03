<?php

namespace Modules\Centers\Policies;

use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Services\Enums\ServiceType;
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

        return $user->isPrimaryOf($center);
    }

    public function delete(User|SystemUser $user, Center $center): bool
    {
        return $this->systemUserCan($user, SystemPermission::DeleteCenter);
    }

    /**
     * Center users: the primary user, or a user granted the service type's
     * `services.{type}.manage` permission (any type when `$type` is null).
     * System users: by permission.
     */
    public function manageServices(User|SystemUser $user, Center $center, ?ServiceType $type = null): bool
    {
        $permissions = $type === null
            ? CenterUserPermission::manageServicesPermissions()
            : [CenterUserPermission::manageServicesFor($type)];

        return $this->canAccessCenter($user, $center, $permissions, SystemPermission::ManageCenterServices);
    }

    /**
     * Center-wide service changes (e.g. gender periods, center-wide blocks)
     * need the manage permission of every service type.
     */
    public function manageAllServices(User|SystemUser $user, Center $center): bool
    {
        if ($user instanceof SystemUser) {
            return $this->systemUserCan($user, SystemPermission::ManageCenterServices);
        }

        return $user->hasAllCenterPermissions($center, CenterUserPermission::manageServicesPermissions());
    }

    public function viewRatings(User|SystemUser $user, Center $center): bool
    {
        return $this->canAccessCenter($user, $center, [CenterUserPermission::ViewRatings], SystemPermission::ViewCenterRatings);
    }

    public function manageBlockedClients(User|SystemUser $user, Center $center): bool
    {
        return $this->canAccessCenter($user, $center, [CenterUserPermission::ManageBlockedClients], SystemPermission::ManageCenterBlockedClients);
    }

    public function manageReservations(User|SystemUser $user, Center $center): bool
    {
        return $this->canAccessCenter($user, $center, [CenterUserPermission::ManageReservations], SystemPermission::ManageCenterReservations);
    }

    /**
     * Reschedule, cancel, complete or mark a reservation as no-show.
     */
    public function editReservations(User|SystemUser $user, Center $center): bool
    {
        return $this->canAccessCenter($user, $center, [CenterUserPermission::EditReservations], SystemPermission::ManageCenterReservations);
    }

    /**
     * Center users: any `purchases.{type}.view` permission; which purchases
     * they see is narrowed by `CenterPurchaseVisibility`.
     */
    public function viewPurchases(User|SystemUser $user, Center $center): bool
    {
        return $this->canAccessCenter($user, $center, CenterUserPermission::viewPurchasesPermissions(), SystemPermission::ManageCenterReservations);
    }

    public function viewPurchasePrices(User|SystemUser $user, Center $center): bool
    {
        return $this->canAccessCenter($user, $center, [CenterUserPermission::ViewPurchasePrices], SystemPermission::ManageCenterReservations);
    }

    /**
     * Center users: the primary user, or a user granted `promotions.manage`.
     * System users: denied; they manage promotions through the admin API.
     */
    public function managePromotions(User|SystemUser $user, Center $center): bool
    {
        return $user instanceof User
            && $user->hasCenterPermission($center, CenterUserPermission::ManagePromotions);
    }

    /**
     * Center users: the primary user, or a user granted `users.manage`.
     * System users: denied.
     */
    public function manageUsers(User|SystemUser $user, Center $center): bool
    {
        return $user instanceof User
            && $user->hasCenterPermission($center, CenterUserPermission::ManageUsers);
    }

    /**
     * @param  array<CenterUserPermission>  $centerPermissions  Any one of them grants access.
     */
    private function canAccessCenter(User|SystemUser $user, Center $center, array $centerPermissions, SystemPermission $systemPermission): bool
    {
        if ($user instanceof SystemUser) {
            return $this->systemUserCan($user, $systemPermission);
        }

        return $user->hasAnyCenterPermission($center, $centerPermissions);
    }

    private function systemUserCan(User|SystemUser $user, SystemPermission $permission): bool
    {
        return $user instanceof SystemUser
            && $user->isActive()
            && $user->hasPermissionTo($permission);
    }
}
