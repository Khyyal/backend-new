<?php

namespace Modules\Promotions\Enums;

enum CouponCheck: string
{
    case Applied = 'applied';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case NotStarted = 'not_started';
    case Exhausted = 'exhausted';
    case ClientLimitReached = 'client_limit_reached';
    case MinSubtotalNotMet = 'min_subtotal_not_met';
    case NotApplicableToCart = 'not_applicable_to_cart';
    case NotEligible = 'not_eligible';
    case CouponNotBetter = 'coupon_not_better';
}
