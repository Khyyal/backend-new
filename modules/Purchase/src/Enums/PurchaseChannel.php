<?php

namespace Modules\Purchase\Enums;

use Illuminate\Http\Request;

/**
 * Where a purchase was made. Platform fees are charged per channel.
 */
enum PurchaseChannel: string
{
    case KhyyalApp = 'khyyal_app';
    case CenterApp = 'center_app';
    case KhyyalWeb = 'khyyal_web';
    case CenterWeb = 'center_web';
    case CenterDashboard = 'center_dashboard';

    /**
     * The channel a client app declares in the `X-Channel` header; an
     * unknown or missing value (or one only the center can use) gives null.
     */
    public static function fromClientRequest(Request $request): ?self
    {
        $channel = self::tryFrom((string) $request->header('X-Channel'));

        return $channel?->isClientFacing() ? $channel : null;
    }

    public function isClientFacing(): bool
    {
        return $this !== self::CenterDashboard;
    }

    /**
     * The center's own white-label app or website.
     */
    public function isCenterBranded(): bool
    {
        return $this === self::CenterApp || $this === self::CenterWeb;
    }
}
