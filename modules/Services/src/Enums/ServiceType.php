<?php

namespace Modules\Services\Enums;

enum ServiceType: string
{
    case RecreationRiding = 'recreation_riding';
    case Visit = 'visit';
    case Event = 'event';
    case Resort = 'resort';
}
