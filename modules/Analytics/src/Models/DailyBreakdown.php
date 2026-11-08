<?php

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Analytics\Enums\BreakdownDimension;

/**
 * Facts of one merchant on one day split by a BreakdownDimension.
 */
class DailyBreakdown extends Model
{
    protected $table = 'analytics_daily_breakdowns';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'dimension' => BreakdownDimension::class,
            'built_at' => 'immutable_datetime',
        ];
    }
}
