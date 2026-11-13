<?php

namespace Modules\Reservations\Enums;

use Modules\Services\Enums\ServiceType;

/**
 * What a stay invoice bills: a horse service or horse care (a price option of
 * that service), or a standalone line with its own name and total.
 */
enum StayInvoiceKind: string
{
    case HorseService = 'horse_service';
    case HorseCare = 'horse_care';
    case Standalone = 'standalone';

    /**
     * The service type a service-based invoice must use; null for standalone.
     */
    public function serviceType(): ?ServiceType
    {
        return match ($this) {
            self::HorseService => ServiceType::HorseService,
            self::HorseCare => ServiceType::HorseCare,
            self::Standalone => null,
        };
    }
}
