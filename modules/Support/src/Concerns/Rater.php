<?php

namespace Modules\Support\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Support\Models\Rating;

trait Rater
{
    public function ratingsGiven(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rater');
    }

    public function rate(
        Model $rateable,
        int $stars,
        ?string $comment = null,
    ): Rating {
        return $rateable->ratings()->create([
            'stars' => $stars,
            'comment' => $comment,
            'rater_id' => $this->getKey(),
            'rater_type' => $this->getMorphClass(),
        ]);
    }
}
