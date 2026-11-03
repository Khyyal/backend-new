<?php

namespace Modules\Promotions\Enums;

enum RedemptionStatus: string
{
    case Applied = 'applied';
    case Released = 'released';
}
