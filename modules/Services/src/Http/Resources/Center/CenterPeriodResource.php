<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\PeriodGender;
use Modules\Services\Models\Schedule;

/**
 * @property Center $resource
 */
class CenterPeriodResource extends JsonResource
{
    #[ResponseField('center_id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('female', 'object', 'Female period; null when not defined.', nullable: true)]
    #[ResponseField('female.days', 'integer[]', 'Weekdays, 0 = Sunday … 6 = Saturday.', example: [2, 4])]
    #[ResponseField('female.hours', 'string[]', '`HH:MM` start/end pairs.', example: ['16:00', '20:00'])]
    #[ResponseField('male', 'object', 'Male period; null when not defined.', nullable: true)]
    #[ResponseField('male.days', 'integer[]', 'Weekdays, 0 = Sunday … 6 = Saturday.', example: [2, 4])]
    #[ResponseField('male.hours', 'string[]', '`HH:MM` start/end pairs.', example: ['16:00', '20:00'])]
    public function toArray(Request $request): array
    {
        $periods = $this->resource->periods->groupBy(fn ($period) => $period->gender->value);

        $result = [];
        foreach (PeriodGender::cases() as $gender) {
            $rows = $periods->get($gender->value);

            $result[$gender->value] = $rows === null ? null : [
                'days' => $rows->pluck('day_of_week')->unique()->sort()->values(),
                'hours' => $rows->sortBy('start_time')
                    ->map(fn ($period) => [Schedule::clockTime($period->start_time), Schedule::clockTime($period->end_time)])
                    ->unique(fn ($pair) => implode('-', $pair))
                    ->flatten()
                    ->values(),
            ];
        }

        return ['center_id' => $this->resource->id, ...$result];
    }
}
