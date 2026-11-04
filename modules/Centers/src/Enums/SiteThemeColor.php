<?php

namespace Modules\Centers\Enums;

/**
 * Allowed keys of a center's `site_theme`; each maps to a hex color.
 */
enum SiteThemeColor: string
{
    case Background = 'background';
    case Foreground = 'foreground';
    case Primary = 'primary';
    case Button = 'button';
    case Secondary = 'secondary';
    case Accent = 'accent';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
