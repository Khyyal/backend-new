<?php

namespace Modules\Promotions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Promotions\Enums\CampaignStatus;
use Modules\Promotions\Models\Campaign;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => ['en' => ucfirst($name), 'ar' => $name],
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'status' => CampaignStatus::Draft->value,
        ];
    }
}
