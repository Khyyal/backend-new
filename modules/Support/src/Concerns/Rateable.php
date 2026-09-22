<?php

namespace Modules\Support\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Support\Models\Rating;

trait Rateable
{
    public function ratings(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rateable');
    }

    public function averageRating(): ?float
    {
        $average = $this->ratings()->avg('stars');

        return $average !== null
            ? round((float) $average, 2)
            : null;
    }

    public function ratingsCount(): int
    {
        return $this->ratings()->count();
    }
}
