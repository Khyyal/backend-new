<?php

namespace Modules\Centers\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Centers\Models\Center;

/**
 * The `ratings` block of a client center read (`include=ratings`):
 * stats over every rating plus the loaded highest ratings.
 * Needs `withRatingStats()` and the `ratings` relation loaded.
 *
 * @property Center $resource
 */
class CenterRatingsResource extends JsonResource
{
    /**
     * @return array{summary: array{average: float|null, count: int}, data: mixed}
     */
    public function toArray(Request $request): array
    {
        $center = $this->resource;

        return [
            'summary' => [
                'average' => $center->rating_avg !== null ? round((float) $center->rating_avg, 2) : null,
                'count' => (int) $center->ratings_count,
            ],
            'data' => CenterRatingResource::collection($center->ratings),
        ];
    }
}
