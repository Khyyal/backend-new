<?php

namespace Modules\Services\Enums;

enum PriceOptionUnit: string
{
    case OPTION = 'option';
    case MINUTE = 'minute';
    case MONTH = 'month';
    case DAY = 'day';
}
