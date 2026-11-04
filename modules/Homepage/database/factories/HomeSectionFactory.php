<?php

namespace Modules\Homepage\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Homepage\Enums\HomeSectionType;
use Modules\Homepage\Enums\PublishStatus;
use Modules\Homepage\Models\HomeSection;

/**
 * @extends Factory<HomeSection>
 */
class HomeSectionFactory extends Factory
{
    protected $model = HomeSection::class;

    public function definition(): array
    {
        $title = fake()->words(2, true);

        return [
            'key' => fake()->unique()->slug(2),
            'type' => HomeSectionType::CuratedCenters,
            'title' => ['en' => ucfirst($title), 'ar' => $title],
            'status' => PublishStatus::Draft,
            'sort_order' => 0,
            'config' => ['limit' => 10],
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => PublishStatus::Published]);
    }

    public function ofType(HomeSectionType $type): static
    {
        return $this->state(['type' => $type]);
    }
}
