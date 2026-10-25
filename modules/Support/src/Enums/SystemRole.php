<?php

namespace Modules\Support\Enums;

enum SystemRole: string
{
    case SuperAdmin = 'super_admin';

    /** @return array<string> */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string> */
    public static function protectedRoleNames(): array
    {
        return self::all();
    }

    public static function isProtected(string $roleName): bool
    {
        return in_array($roleName, self::protectedRoleNames(), true);
    }
}
