<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Support\Models\Rating;

/**
 * @property Rating $resource
 */
class RatingResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Rating ID.', example: 21)]
    #[ResponseField('client', 'string', 'Rater full name.', nullable: true, example: 'Noura Al-Qahtani')]
    #[ResponseField('stars', 'integer', 'Stars, 1-5.', example: 5)]
    #[ResponseField('comment', 'string', 'Optional comment.', nullable: true, example: 'Great trainers!')]
    #[ResponseField('rated_at', 'string', 'When it was rated (ISO-8601).', example: '2026-05-20T09:30:00.000000Z')]
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
