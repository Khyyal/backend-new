<?php

namespace Modules\Homepage\Enums;

enum BannerActionType: string
{
    case None = 'none';
    case Url = 'url';
    case Center = 'center';
    case Service = 'service';
    case Promotion = 'promotion';
    case Campaign = 'campaign';

    /** The table the action target lives in, for actions that point at a record. */
    public function targetTable(): ?string
    {
        return match ($this) {
            self::Center => 'centers',
            self::Service => 'services',
            self::Promotion => 'promotions',
            self::Campaign => 'campaigns',
            default => null,
        };
    }
}
