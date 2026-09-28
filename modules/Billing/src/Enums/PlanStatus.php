<?php

namespace Modules\Billing\Enums;

enum PlanStatus: string
{
    case Active = 'active';
    case InActive = 'in_active';
}
