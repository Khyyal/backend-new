<?php

namespace Modules\Centers\Enums;

enum CenterOrder: string
{
    case MostPopular = 'most_popular';
    case Nearest = 'nearest';
}
