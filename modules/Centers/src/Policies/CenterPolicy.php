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

    public function export(User|SystemUser $user): bool
    {
        return $this->systemUserCan($user, SystemPermission::ExportCenters);
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

    /**
     * A center's own primary user deleting their account.
     */
    public function deleteOwn(User|SystemUser $user, Center $center): bool
    {
        return $user instanceof User && $user->isPrimaryOf($center);
    }

    public function delete(User|SystemUser $user, Center $center): bool
    {
        return $this->systemUserCan($user, SystemPermission::DeleteCenter);
    }

    public function restore(User|SystemUser $user, Center $center): bool
    {
        return $this->systemUserCan($user, SystemPermission::RestoreCenter);
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

    public function manageHorses(User|SystemUser $user, Center $center): bool
    {
        return $this->canAccessCenter($user, $center, [CenterUserPermission::ManageHorses], SystemPermission::ManageCenterHorses);
    }

    /**
     * The horses and stabling stays board: either horse or reservation access.
     */
    public function viewHorseStays(User|SystemUser $user, Center $center): bool
    {
        if ($user instanceof SystemUser) {
            return $this->systemUserCan($user, SystemPermission::ManageCenterHorses)
                || $this->systemUserCan($user, SystemPermission::ManageCenterReservations);
        }

        return $user->hasAnyCenterPermission($center, [CenterUserPermission::ManageHorses, CenterUserPermission::ManageReservations]);
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
     * Center users: `purchases.export` on top of seeing purchases (the export
     * is narrowed by purchase visibility). System users: `purchases.export`.
     */
    public function exportPurchases(User|SystemUser $user, Center $center): bool
    {
        if ($user instanceof SystemUser) {
            return $this->systemUserCan($user, SystemPermission::ExportPurchases);
        }

        return $this->viewPurchases($user, $center)
            && $user->hasCenterPermission($center, CenterUserPermission::ExportPurchases);
    }

    /**
     * Center users: `clients.export` on top of the access to the client list
     * (`reservations.manage`). System users: denied; they export every client
     * through the admin API.
     */
    public function exportClients(User|SystemUser $user, Center $center): bool
    {
        return $user instanceof User
            && $this->manageReservations($user, $center)
            && $user->hasCenterPermission($center, CenterUserPermission::ExportClients);
    }

    /**
     * Center users: the primary user, or a user granted `analytics.view`.
     * Analytics are center-wide, so they are not narrowed by purchase visibility.
     */
    public function viewAnalytics(User|SystemUser $user, Center $center): bool
    {
        return $this->canAccessCenter($user, $center, [CenterUserPermission::ViewAnalytics], SystemPermission::ManageCenterReservations);
    }

    /**
     * Center users: the primary user, or a user granted `payouts.view`.
     * System users: by `payouts.manage`.
     */
    public function viewPayouts(User|SystemUser $user, Center $center): bool
    {
        return $this->canAccessCenter($user, $center, [CenterUserPermission::ViewPayouts], SystemPermission::ManagePayouts);
    }

    /**
     * Issue a login OTP for one of the center's users (shown to the admin,
     * no SMS). System users only.
     */
    public function createOtp(User|SystemUser $user, Center $center): bool
    {
        return $this->systemUserCan($user, SystemPermission::CreateOtp);
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
     * System users managing a center's users through the admin API: by
     * `centers.update`. Center users: denied; they use `manageUsers`.
     */
    public function manageUsersAsAdmin(User|SystemUser $user, Center $center): bool
    {
        return $this->systemUserCan($user, SystemPermission::UpdateCenter);
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
