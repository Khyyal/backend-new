<?php

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Additive facts of one merchant on one UTC day, rebuilt by BuildDailyStats.
 * `center_id` 0 is the platform merchant.
 */
class DailyCenterStat extends Model
{
    public const PLATFORM_MERCHANT = 0;

    protected $table = 'analytics_daily_center_stats';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'built_at' => 'immutable_datetime',
        ];
    }
}
