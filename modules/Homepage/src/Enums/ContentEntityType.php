<?php

namespace Modules\Homepage\Enums;

/**
 * What the apps report impressions and clicks for.
 */
enum ContentEntityType: string
{
    case HomeSection = 'home_section';
    case Banner = 'banner';
    case Center = 'center';
    case Service = 'service';
    case Promotion = 'promotion';
    case Campaign = 'campaign';

    public function table(): string
    {
        return match ($this) {
            self::HomeSection => 'home_sections',
            self::Banner => 'banners',
            self::Center => 'centers',
            self::Service => 'services',
            self::Promotion => 'promotions',
            self::Campaign => 'campaigns',
        };
    }
}
