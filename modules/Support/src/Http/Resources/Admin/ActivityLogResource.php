<?php

namespace Modules\Support\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/**
 * @property Activity $resource
 */
class ActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $causer = $this->resource->causer;

        return [
            'id' => $this->resource->id,
            'log_name' => $this->resource->log_name,
            'event' => $this->resource->event,
            'subject_id' => $this->resource->subject_id,
            'causer' => $causer === null ? null : [
                'type' => class_basename($causer),
                'id' => $causer->getKey(),
                'name' => $causer->name ?? null,
            ],
            'changes' => $this->resource->attribute_changes,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
