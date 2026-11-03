<?php

namespace Modules\Promotions\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
