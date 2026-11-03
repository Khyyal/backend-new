<?php

namespace Modules\Notifications\Enums;

enum CampaignChannel: string
{
    case Push = 'push';
    case WhatsApp = 'whatsapp';
}
