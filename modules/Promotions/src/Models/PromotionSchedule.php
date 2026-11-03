<?php

namespace Modules\Promotions\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A weekly window (ISO day 1 = Monday … 7 = Sunday). A window whose end is
 * not after its start wraps past midnight into the next day.
 */
class PromotionSchedule extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'promotion_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * Whether the given moment, already in the business timezone, falls in this window.
     */
    public function covers(CarbonInterface $at): bool
    {
        $time = $at->format('H:i:s');
        $start = $this->normalizeTime($this->start_time);
        $end = $this->normalizeTime($this->end_time);
        $day = $at->dayOfWeekIso;

        if ($start < $end) {
            return $this->day_of_week === $day && $time >= $start && $time < $end;
        }

        if ($this->day_of_week === $day && $time >= $start) {
            return true;
        }

        $previousDay = $day === 1 ? 7 : $day - 1;

        return $this->day_of_week === $previousDay && $time < $end;
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
