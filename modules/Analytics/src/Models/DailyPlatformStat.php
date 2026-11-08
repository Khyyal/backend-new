<?php

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide distinct counts and end-of-day snapshots of one UTC day.
 * Also the subject of AnalyticsPolicy, which guards every analytics endpoint.
 */
class DailyPlatformStat extends Model
{
    protected $table = 'analytics_daily_platform_stats';

    protected $primaryKey = 'date';

    protected $keyType = 'string';

    public $incrementing = false;

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
