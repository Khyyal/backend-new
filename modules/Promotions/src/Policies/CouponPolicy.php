<?php

namespace Modules\Promotions\Policies;

use Modules\Promotions\Models\Coupon;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

class CouponPolicy
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

    public function view(SystemUser $user, Coupon $model): bool
    {
        return $this->manages($user);
    }

    public function create(SystemUser $user): bool
    {
        return $this->manages($user);
    }

    public function update(SystemUser $user, Coupon $model): bool
    {
        return $this->manages($user);
    }

    public function delete(SystemUser $user, Coupon $model): bool
    {
        return $this->manages($user);
    }

    private function manages(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ManageCoupons);
    }
}
