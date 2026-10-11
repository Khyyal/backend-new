<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Models\Block;

/**
 * @property Block $resource
 */
class BlockedClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'client_id' => $this->resource->blockable_id,
            'client' => $this->resource->blockable?->full_name,
            'phone_number' => $this->resource->blockable?->phone_number,
            'reason' => $this->resource->reason,
            'blocked_at' => $this->resource->created_at,
        ];
    }
}
