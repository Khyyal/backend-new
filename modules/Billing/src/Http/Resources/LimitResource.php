<?php

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;

class LimitResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Limit ID.', example: 2)]
    #[ResponseField('key', 'string', 'Unique machine key checked by the code.', example: 'max_services')]
    #[ResponseField('name', 'string', 'Display name.', example: 'Maximum services')]
    #[ResponseField('value', 'integer', 'Limit value on the plan. Only when listed under a plan.', required: false, nullable: true, example: 20)]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601, UTC).', example: '2026-06-01T00:00:00.000000Z')]
    #[ResponseField('updated_at', 'string', 'Last update (ISO-8601, UTC).', example: '2026-06-01T00:00:00.000000Z')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'value' => $this->whenPivotLoaded('plan_limits', fn () => $this->pivot?->value !== null ? (int) $this->pivot->value : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
