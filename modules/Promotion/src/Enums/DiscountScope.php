<?php

namespace Modules\Promotion\Enums;

enum DiscountScope: string
{
    case All = 'all';
    case SpecificItems = 'specific_items';
}
