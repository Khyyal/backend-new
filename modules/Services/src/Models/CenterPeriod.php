<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Centers\Models\Center;
use Modules\Services\Database\Factories\CenterPeriodFactory;
use Modules\Services\Enums\PeriodGender;

/**
 * A gender period defined by a center; it applies to all of the center's services.
 */
class CenterPeriod extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'center_id',
        'gender',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'gender' => PeriodGender::class,
            'day_of_week' => 'integer',
        ];
    }

    protected static function newFactory(): CenterPeriodFactory
    {
        return CenterPeriodFactory::new();
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }
}
