<?php

namespace Modules\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centers\Models\Rating;

class RatingFactory extends Factory
{
    protected $model = Rating::class;

    public function definition(): array
    {
        return [
            'stars' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional()->sentence(),
        ];
    }

    public function forRateable($rateable): static
    {
        return $this->state([
            'rateable_id' => $rateable->getKey(),
            'rateable_type' => $rateable->getMorphClass(),
        ]);
    }

    public function byRater($rater): static
    {
        return $this->state([
            'rater_id' => $rater->getKey(),
            'rater_type' => $rater->getMorphClass(),
        ]);
    }

    public function fiveStars(): static
    {
        return $this->state([
            'stars' => 5,
        ]);
    }

    public function oneStar(): static
    {
        return $this->state([
            'stars' => 1,
        ]);
    }
}
