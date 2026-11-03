<?php

namespace Modules\Notifications\Enums;

/**
 * What the app opens when a push campaign is tapped.
 */
enum CampaignActionType: string
{
    case None = 'none';
    case Center = 'center';
    case Service = 'service';
    case Url = 'url';
}
