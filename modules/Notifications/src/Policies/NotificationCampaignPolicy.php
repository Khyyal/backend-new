<?php

namespace Modules\Notifications\Policies;

use Modules\Notifications\Models\NotificationCampaign;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

class NotificationCampaignPolicy
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

    public function view(SystemUser $user, NotificationCampaign $campaign): bool
    {
        return $this->manages($user);
    }

    public function create(SystemUser $user): bool
    {
        return $this->manages($user);
    }

    public function update(SystemUser $user, NotificationCampaign $campaign): bool
    {
        return $this->manages($user);
    }

    public function send(SystemUser $user, NotificationCampaign $campaign): bool
    {
        return $this->manages($user);
    }

    public function delete(SystemUser $user, NotificationCampaign $campaign): bool
    {
        return $this->manages($user);
    }

    private function manages(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ManageNotificationCampaigns);
    }
}
