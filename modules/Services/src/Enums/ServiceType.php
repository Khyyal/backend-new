<?php

namespace Modules\Services\Enums;

enum ServiceType: string
{
    case RecreationRiding = 'recreation_riding';
    case Visit = 'visit';
    case Event = 'event';
    case Resort = 'resort';
    case HorseCare = 'horse_care';
    case HorseService = 'horse_service';
}
