<?php

namespace Modules\Services\Enums;

enum BlockTimeScope: string
{
    case AllDay = 'all_day';
    case SpecificTime = 'specific_time';
}
