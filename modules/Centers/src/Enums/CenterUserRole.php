<?php

namespace Modules\Centers\Enums;

enum CenterUserRole: string
{
    case Owner = 'owner';


    /** @return array<string> */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string> */
    public static function protectedRoleNames(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isProtected(string $roleName): bool
    {
        return in_array($roleName, self::protectedRoleNames(), true);
    }
}
