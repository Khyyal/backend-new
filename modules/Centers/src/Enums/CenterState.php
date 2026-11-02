<?php

namespace Modules\Centers\Enums;

/**
 * Admin-controlled account state of a center. Only active centers are shown
 * to clients and can use their center-scoped endpoints. Independent of
 * `CenterStatus`, which is the center's own listing visibility.
 */
enum CenterState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Blocked = 'blocked';
}
