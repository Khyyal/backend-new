<?php

namespace Modules\Centers\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Centers\Models\Center;
use Modules\Support\Models\Rating;

/**
 * A rating as the client who gave it sees it.
 *
 * @property Rating $resource
 */
class RatingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'integer', 'Rating ID.', example: 21)]
    #[ResponseField('stars', 'integer', 'Stars, 1-5.', example: 5)]
    #[ResponseField('comment', 'string', 'Optional comment.', nullable: true, example: 'Great trainers!')]
    #[ResponseField('center', 'object', 'The rated center (`id`, `name`); null when deleted.', nullable: true, example: ['id' => 8, 'name' => 'Al Fursan Stables'])]
    #[ResponseField('rated_at', 'string', 'When it was rated (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('updated_at', 'string', 'When it was last edited (ISO-8601).', example: '2026-05-21T10:00:00+00:00')]
    public function toArray(Request $request): array
    {
        $rating = $this->resource;
        $center = $rating->rateable instanceof Center ? $rating->rateable : null;

        return [
            'id' => $rating->id,
            'stars' => $rating->stars,
            'comment' => $rating->comment,
            'center' => $center === null ? null : [
                'id' => $center->id,
                'name' => $center->name,
            ],
            'rated_at' => $rating->created_at?->toIso8601String(),
            'updated_at' => $rating->updated_at?->toIso8601String(),
        ];
    }
}
