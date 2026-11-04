<?php

namespace Modules\Centers\Enums;

/**
 * Allowed keys of a center's `social_links`.
 */
enum SocialPlatform: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Whatsapp = 'whatsapp';
    case Tiktok = 'tiktok';
    case Snapchat = 'snapchat';
    case X = 'x';
    case Youtube = 'youtube';
    case Website = 'website';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
