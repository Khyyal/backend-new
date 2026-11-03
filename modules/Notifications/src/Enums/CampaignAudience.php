<?php

namespace Modules\Notifications\Enums;

use Illuminate\Database\Eloquent\Model;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;

/**
 * Who a campaign reaches. Center targets are centers; every active staff user of a targeted center receives it.
 */
enum CampaignAudience: string
{
    case Clients = 'clients';
    case Centers = 'centers';

    /**
     * The model that selected target ids refer to.
     *
     * @return class-string<Model>
     */
    public function targetModel(): string
    {
        return match ($this) {
            self::Clients => Client::class,
            self::Centers => Center::class,
        };
    }
}
