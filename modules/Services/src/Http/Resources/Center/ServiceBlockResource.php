<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\ServiceBlock;

/**
 * @property ServiceBlock $resource
 */
class ServiceBlockResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Block ID.', example: 4)]
    #[ResponseField('center_id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('service_id', 'integer', 'Blocked service; null when every service of the center is blocked.', nullable: true, example: 31)]
    #[ResponseField('reason', 'string', 'Reason shown internally.', example: 'Maintenance')]
    #[ResponseField('start_date', 'string', 'First blocked day, `Y-m-d`.', example: '2026-06-10')]
    #[ResponseField('end_date', 'string', 'Last blocked day, `Y-m-d`.', example: '2026-06-11')]
    #[ResponseField('time_scope', 'string', '`all_day` or `specific_time` (only `hours`).', enum: BlockTimeScope::class, example: 'specific_time')]
    #[ResponseField('hours', 'string[]', '`HH:MM` start/end pairs blocked each day; empty for `all_day`.', example: ['09:00', '12:00'])]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-05-20T09:30:00.000000Z')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'center_id' => $this->resource->center_id,
            'service_id' => $this->resource->service_id,
            'reason' => $this->resource->reason,
            'start_date' => $this->resource->start_date->format('Y-m-d'),
            'end_date' => $this->resource->end_date->format('Y-m-d'),
            'time_scope' => $this->resource->time_scope,
            'hours' => $this->resource->time_scope === BlockTimeScope::SpecificTime
                ? $this->resource->periods->sortBy('start_time')
                    ->flatMap(fn ($period) => [Schedule::clockTime($period->start_time), Schedule::clockTime($period->end_time)])
                    ->values()
                : [],
            'created_at' => $this->resource->created_at,
        ];
    }
}
