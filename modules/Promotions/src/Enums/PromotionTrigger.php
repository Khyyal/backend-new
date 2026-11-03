<?php

namespace Modules\Promotions\Enums;

enum PromotionTrigger: string
{
    case Automatic = 'automatic';
    case Coupon = 'coupon';
}
