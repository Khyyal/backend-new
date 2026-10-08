<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Models\ServiceBlock;

/**
 * @property ServiceBlock $resource
 */
class ServiceBlockResource extends JsonResource
{
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
                    ->flatMap(fn ($period) => [substr($period->start_time, 0, 5), substr($period->end_time, 0, 5)])
                    ->values()
                : [],
            'created_at' => $this->resource->created_at,
        ];
    }
}
