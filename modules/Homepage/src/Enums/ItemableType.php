<?php

namespace Modules\Homepage\Enums;

use Illuminate\Database\Eloquent\Model;
use Modules\Centers\Models\Center;
use Modules\Promotions\Models\Campaign;
use Modules\Promotions\Models\Promotion;
use Modules\Services\Models\Service;

/**
 * What an admin can pick into a curated section.
 */
enum ItemableType: string
{
    case Center = 'center';
    case Service = 'service';
    case Promotion = 'promotion';
    case Campaign = 'campaign';

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Center => Center::class,
            self::Service => Service::class,
            self::Promotion => Promotion::class,
            self::Campaign => Campaign::class,
        };
    }
}
