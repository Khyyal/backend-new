<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\Trainer;

/**
 * @property Trainer $resource
 */
class TrainerResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Trainer ID.', example: 6)]
    #[ResponseField('center_id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('name', 'string', 'Trainer name.', example: 'Fahad')]
    #[ResponseField('max_tickets_per_hour', 'integer', 'Trainees per hour; null for no limit.', nullable: true, example: 2)]
    #[ResponseField('max_tickets_per_hour_by_day', 'object', 'Per-weekday overrides of `max_tickets_per_hour` (weekday => limit); null when none.', nullable: true, example: ['5' => 4])]
    #[ResponseField('days', 'integer[]', 'Weekdays it runs, Carbon numbering (0 = Sunday … 6 = Saturday).', example: [0, 1, 2, 3, 4])]
    #[ResponseField('hours', 'string[]', 'Flat list of `HH:MM` start/end pairs applied to every day in `days`.', example: ['09:00', '12:00', '16:00', '20:00'])]
    public function toArray(Request $request): array
    {
        $schedules = $this->resource->schedules->sortBy([['day_of_week', 'asc'], ['start_time', 'asc']]);
        $firstDay = $schedules->first()?->day_of_week;

        return [
            'id' => $this->resource->id,
            'center_id' => $this->resource->center_id,
            'name' => $this->resource->name,
            'max_tickets_per_hour' => $this->resource->max_tickets_per_hour,
            'max_tickets_per_hour_by_day' => $this->resource->max_tickets_per_hour_by_day === null
                ? null
                : (object) $this->resource->max_tickets_per_hour_by_day,
            'days' => $schedules->pluck('day_of_week')->unique()->values(),
            'hours' => $schedules->where('day_of_week', $firstDay)
                ->flatMap(fn ($slot) => [Schedule::clockTime($slot->start_time), Schedule::clockTime($slot->end_time)])
                ->values(),

        ];
    }
}
