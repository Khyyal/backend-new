<?php

namespace Modules\Centers\Enums;

enum CenterUserPermission : string
{


    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
