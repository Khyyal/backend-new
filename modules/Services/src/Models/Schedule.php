<?php

namespace Modules\Services\Models;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasFactory , SoftDeletes;

    protected $fillable = [
        'schedulable_type',
        'schedulable_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    public $timestamps = false;

    /**
     * Stored end time of a window that runs until midnight. Clients send it as `00:00`.
     */
    public const string END_OF_DAY = '24:00:00';

    /**
     * Stored form of a window end: `00:00` means the end of the day.
     */
    public static function endTime(string $end): string
    {
        return in_array($end, ['00:00', '00:00:00', '24:00', self::END_OF_DAY], true) ? self::END_OF_DAY : $end;
    }

    /**
     * `H:i` form of a stored time as clients send it, with the end of the day back as `00:00`.
     */
    public static function clockTime(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        $clock = substr($time, 0, 5);

        return $clock === '24:00' ? '00:00' : $clock;
    }

    /**
     * Checks a flat `start, end` list of `H:i` times: an even count, and every end after its
     * start. An end of `00:00` is midnight at the end of the day.
     *
     * @param  string  $attribute  the validated key of the list, e.g. `hours` or `female.hours`
     */
    public static function validateHourPairs(Validator $validator, mixed $hours, string $attribute): void
    {
        if (! is_array($hours) || $validator->errors()->has("$attribute.*") || $validator->errors()->has($attribute)) {
            return;
        }

        if (count($hours) % 2 !== 0) {
            $validator->errors()->add($attribute, 'hours must contain start and end pairs.');

            return;
        }

        foreach (array_chunk($hours, 2) as $index => [$start, $end]) {
            if ($start >= substr(self::endTime($end), 0, 5)) {
                $validator->errors()->add("$attribute.".($index * 2 + 1), 'Each end time must be after its start time.');
            }
        }
    }

    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForDay(
        Builder $query,
        int $dayOfWeek,
    ): Builder {
        return $query
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time');
    }
}
