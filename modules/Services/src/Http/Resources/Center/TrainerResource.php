<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\Trainer;

/**
 * @property Trainer $resource
 */
class TrainerResource extends JsonResource
{
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
