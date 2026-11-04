<?php

namespace Modules\Homepage\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Homepage\Enums\BannerActionType;
use Modules\Homepage\Enums\HomeSectionType;
use Modules\Homepage\Enums\PublishStatus;
use Modules\Homepage\Models\Banner;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    protected $model = Banner::class;

    public function definition(): array
    {
        $title = fake()->words(3, true);

        return [
            'home_section_id' => HomeSectionFactory::new()->ofType(HomeSectionType::BannerCarousel),
            'title' => ['en' => ucfirst($title), 'ar' => $title],
            'action_type' => BannerActionType::None,
            'status' => PublishStatus::Draft,
            'sort_order' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => PublishStatus::Published]);
    }
}
