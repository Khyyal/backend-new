<?php

namespace Modules\Promotions\Enums;

enum PromotionStacking: string
{
    case Exclusive = 'exclusive';
    case Stackable = 'stackable';
}
