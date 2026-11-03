<?php

namespace Modules\Promotions\Policies;

use Modules\Promotions\Models\Promotion;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

/**
 * Admin access to promotions. Center users go through CenterPolicy::managePromotions.
 */
class PromotionPolicy
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
        return $user->hasPermissionTo(SystemPermission::ViewAnyPromotions);
    }

    public function view(SystemUser $user, Promotion $promotion): bool
    {
        return $user->hasPermissionTo(SystemPermission::ViewPromotion);
    }

    public function create(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::CreatePromotion);
    }

    public function update(SystemUser $user, Promotion $promotion): bool
    {
        return $user->hasPermissionTo(SystemPermission::UpdatePromotion);
    }

    public function delete(SystemUser $user, Promotion $promotion): bool
    {
        return $user->hasPermissionTo(SystemPermission::DeletePromotion);
    }

    public function activate(SystemUser $user, Promotion $promotion): bool
    {
        return $user->hasPermissionTo(SystemPermission::ActivatePromotion);
    }
}
