<?php

namespace Modules\Support\Enums;

enum SystemPermission: string
{
    case ViewAnySystemUsers = 'system_users.view_any';
    case ViewSystemUser = 'system_users.view';
    case CreateSystemUser = 'system_users.create';
    case UpdateSystemUser = 'system_users.update';
    case DeleteSystemUser = 'system_users.delete';
    case RestoreSystemUser = 'system_users.restore';
    case ForceDeleteSystemUser = 'system_users.force_delete';
    case AssignSystemUserRoles = 'system_users.assign_roles';

    case ViewAnySystemRoles = 'system_roles.view_any';
    case ViewSystemRole = 'system_roles.view';
    case CreateSystemRole = 'system_roles.create';
    case UpdateSystemRole = 'system_roles.update';
    case DeleteSystemRole = 'system_roles.delete';

    case ViewAnyCenters = 'centers.view_any';
    case ViewCenter = 'centers.view';
    case CreateCenter = 'centers.create';
    case UpdateCenter = 'centers.update';
    case DeleteCenter = 'centers.delete';
    case ManageCenterServices = 'centers.manage_services';
    case ViewCenterRatings = 'centers.view_ratings';
    case ManageCenterBlockedClients = 'centers.manage_blocked_clients';

    /** @return array<string> */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
