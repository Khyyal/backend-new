<?php

namespace Modules\Homepage\Enums;

enum ContentEventType: string
{
    case Impression = 'impression';
    case Click = 'click';
}
