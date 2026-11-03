<?php

namespace Modules\Notifications\Policies;

use Modules\Notifications\Models\WhatsAppTemplate;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

class WhatsAppTemplatePolicy
{
    /**
     * Inactive system users are denied everything.
     */
    public function before(SystemUser $user): ?bool
    {
        return $user->isActive() ? null : false;
    }

    /**
     * Campaign managers can list templates to pick one, even without managing them.
     */
    public function viewAny(SystemUser $user): bool
    {
        return $this->manages($user) || $user->hasPermissionTo(SystemPermission::ManageNotificationCampaigns);
    }

    public function view(SystemUser $user, WhatsAppTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    public function create(SystemUser $user): bool
    {
        return $this->manages($user);
    }

    public function update(SystemUser $user, WhatsAppTemplate $template): bool
    {
        return $this->manages($user);
    }

    public function delete(SystemUser $user, WhatsAppTemplate $template): bool
    {
        return $this->manages($user);
    }

    private function manages(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ManageWhatsAppTemplates);
    }
}
