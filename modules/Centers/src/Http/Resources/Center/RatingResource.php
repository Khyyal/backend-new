<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Models\Rating;

/**
 * @property Rating $resource
 */
class RatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'client' => $this->resource->rater?->full_name,
            'stars' => $this->resource->stars,
            'comment' => $this->resource->comment,
            'rated_at' => $this->resource->created_at,
        ];
    }
}
