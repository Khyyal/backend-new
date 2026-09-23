<?php

namespace Modules\Support\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Centers\Models\Rating;

trait Rater
{
    public function ratingsGiven(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rater');
    }
}
