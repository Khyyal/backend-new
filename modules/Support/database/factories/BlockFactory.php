<?php

namespace Modules\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Modules\Support\Models\Block;

class BlockFactory extends Factory
{
    protected $model = Block::class;

    public function definition(): array
    {
        return [
            'reason' => fake()->sentence(),
        ];
    }

    public function forBlockable(Model $blockable): static
    {
        return $this->state([
            'blockable_id' => $blockable->getKey(),
            'blockable_type' => $blockable->getMorphClass(),
        ]);
    }

    public function byBlocker(Model $blocker): static
    {
        return $this->state([
            'blocker_id' => $blocker->getKey(),
            'blocker_type' => $blocker->getMorphClass(),
        ]);
    }
}
