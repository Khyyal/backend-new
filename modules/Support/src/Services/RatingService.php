<?php

namespace Modules\Support\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Centers\Models\Rating;

class RatingService
{
    /**
     * Create a rating.
     */
    public function create(
        Model $rater,
        Model $rateable,
        int $stars,
        ?string $comment = null,
    ): Rating {
        $this->validateStars($stars);

        return $rateable->ratings()->create([
            'stars' => $stars,
            'comment' => $comment,
            'rater_id' => $rater->getKey(),
            'rater_type' => $rater->getMorphClass(),
        ]);
    }

    /**
     * Update an existing rating.
     */
    public function update(
        Rating $rating,
        int $stars,
        ?string $comment = null,
    ): Rating {
        $this->validateStars($stars);

        $rating->update([
            'stars' => $stars,
            'comment' => $comment,
        ]);

        return $rating->refresh();
    }

    /**
     * Delete a rating.
     */
    public function delete(Rating $rating): bool
    {
        return $rating->delete();
    }

    /**
     * Get ratings for a rateable model.
     */
    public function getFor(Model $rateable): Collection
    {
        return $rateable->ratings()
            ->latest()
            ->get();
    }

    /**
     * Get average rating.
     */
    public function average(Model $rateable): ?float
    {
        $average = $rateable->ratings()->avg('stars');

        return $average !== null
            ? round((float) $average, 2)
            : null;
    }

    /**
     * Get number of ratings.
     */
    public function count(Model $rateable): int
    {
        return $rateable->ratings()->count();
    }

    /**
     * Get rating statistics.
     */
    public function statistics(Model $rateable): array
    {
        $query = $rateable->ratings();

        return [
            'average' => $query->avg('stars'),
            'count' => $query->count(),
            'five_stars' => (clone $query)->where('stars', 5)->count(),
            'four_stars' => (clone $query)->where('stars', 4)->count(),
            'three_stars' => (clone $query)->where('stars', 3)->count(),
            'two_stars' => (clone $query)->where('stars', 2)->count(),
            'one_star' => (clone $query)->where('stars', 1)->count(),
        ];
    }

    private function validateStars(int $stars): void
    {
        if ($stars < 1 || $stars > 5) {
            throw new InvalidArgumentException(
                'Rating stars must be between 1 and 5.'
            );
        }
    }
}
