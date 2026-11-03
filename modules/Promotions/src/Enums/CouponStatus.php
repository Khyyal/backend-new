<?php

namespace Modules\Promotions\Enums;

enum CouponStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
