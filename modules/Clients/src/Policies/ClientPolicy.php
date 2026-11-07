<?php

namespace Modules\Clients\Policies;

use Modules\Clients\Models\Client;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

class ClientPolicy
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
        return $user->hasPermissionTo(SystemPermission::ViewAnyClients);
    }

    public function view(SystemUser $user, Client $client): bool
    {
        return $user->hasPermissionTo(SystemPermission::ViewClient);
    }
}
