<?php

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;

class FeatureResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Feature ID.', example: 3)]
    #[ResponseField('key', 'string', 'Unique machine key checked by the code.', example: 'custom_site')]
    #[ResponseField('name', 'string', 'Display name.', example: 'Custom website')]
    #[ResponseField('is_quota', 'boolean', 'Whether it is a countable quota rather than an on/off flag.', example: false)]
    #[ResponseField('enabled', 'boolean', 'Enabled on the plan. Only when listed under a plan.', required: false, example: true)]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601, UTC).', example: '2026-06-01T00:00:00.000000Z')]
    #[ResponseField('updated_at', 'string', 'Last update (ISO-8601, UTC).', example: '2026-06-01T00:00:00.000000Z')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'is_quota' => (bool) $this->is_quota,
            'enabled' => $this->whenPivotLoaded('plan_features', fn () => (bool) $this->pivot?->enabled),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
