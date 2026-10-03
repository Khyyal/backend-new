<?php

namespace Modules\Purchase\Enums;

enum PurchaseSource: string
{
    case Client = 'client';
    case Center = 'center';
    case System = 'system';
}
