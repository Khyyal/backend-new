<?php

namespace Modules\Promotions\Enums;

enum TargetMode: string
{
    case Include = 'include';
    case Exclude = 'exclude';
}
