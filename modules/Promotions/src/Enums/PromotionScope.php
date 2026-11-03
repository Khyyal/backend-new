<?php

namespace Modules\Promotions\Enums;

enum PromotionScope: string
{
    case Item = 'item';
    case Order = 'order';
}
