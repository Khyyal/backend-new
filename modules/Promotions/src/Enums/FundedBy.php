<?php

namespace Modules\Promotions\Enums;

enum FundedBy: string
{
    case Platform = 'platform';
    case Center = 'center';
    case Shared = 'shared';
}
