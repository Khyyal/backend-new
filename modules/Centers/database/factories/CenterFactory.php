<?php

namespace Modules\Centers\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Models\Center;
use Modules\Support\Models\City;

class CenterFactory extends Factory
{
    protected $model = Center::class;

    public function definition(): array
    {
        return [
            'city_id' => City::factory(),

            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),

            'description' => fake()->optional()->paragraph(),

            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),

            'address' => fake()->address(),
            'email' => fake()->unique()->safeEmail(),

            'points' => 0,

            'status' => fake()->randomElement([
                'visible',
                'invisible',
            ]),

            'state' => CenterState::Active,

            'social_links' => null,
            'site_theme' => null,
            'custom_site_enabled' => false,
            'domain' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(['state' => CenterState::Pending]);
    }

    public function withCustomSite(): static
    {
        return $this->state(fn () => [
            'custom_site_enabled' => true,
            'domain' => fake()->unique()->domainName(),
            'site_theme' => [
                'background' => '#ffffff',
                'foreground' => '#111111',
                'primary' => '#0f766e',
                'button' => '#0f766e',
                'secondary' => '#64748b',
                'accent' => '#f59e0b',
            ],
        ]);
    }

    public function blocked(): static
    {
        return $this->state(['state' => CenterState::Blocked]);
    }
}
