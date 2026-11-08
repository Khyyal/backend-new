<?php

namespace Modules\Analytics\Enums;

/**
 * What the apps report when a client engages with a center.
 */
enum CenterEngagementAction: string
{
    case CenterClick = 'center_click';
    case ServiceClick = 'service_click';
    case MapClick = 'map_click';
    case WhatsappClick = 'whatsapp_click';

    /**
     * The analytics_daily_center_engagement counter this action bumps.
     */
    public function column(): string
    {
        return match ($this) {
            self::CenterClick => 'center_clicks',
            self::ServiceClick => 'service_clicks',
            self::MapClick => 'map_clicks',
            self::WhatsappClick => 'whatsapp_clicks',
        };
    }

    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        return array_map(fn (self $action): string => $action->column(), self::cases());
    }
}
