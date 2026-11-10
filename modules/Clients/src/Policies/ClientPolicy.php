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

    public function export(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ExportClients);
    }

    public function view(SystemUser $user, Client $client): bool
    {
        return $user->hasPermissionTo(SystemPermission::ViewClient);
    }

    /**
     * Issue a login OTP for the client (shown to the admin, no SMS).
     */
    public function createOtp(SystemUser $user, Client $client): bool
    {
        return $user->hasPermissionTo(SystemPermission::CreateOtp);
    }
}
