<?php

namespace Modules\Promotions\Enums;

enum DiscountType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case FixedPrice = 'fixed_price';
}
