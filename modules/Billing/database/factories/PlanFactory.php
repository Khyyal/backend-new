<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Models\Feature;
use Modules\Billing\Models\Limit;
use Modules\Billing\Models\Plan;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $nameEn = fake()->words(3, true);

        return [
            'name' => [
                'en' => $nameEn,
                'ar' => fake('ar_SA')->words(3, true),
            ],
            'slug' => Str::slug($nameEn).'-'.fake()->unique()->randomNumber(3),
            'description' => fake()->boolean(60) ? [
                'en' => fake()->sentence(),
                'ar' => fake('ar_SA')->sentence(),
            ] : null,
            'price' => fake()->randomFloat(2, 0, 500),
            'billing_interval' => fake()->randomElement(BillingInterval::cases()),
            'display_features' => fake()->boolean(50) ? [
                'en' => [fake()->sentence(), fake()->sentence()],
                'ar' => [fake('ar_SA')->sentence(), fake('ar_SA')->sentence()],
            ] : null,
            'status' => PlanStatus::Active,
            'trial_days' => fake()->numberBetween(0, 30),
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn () => ['billing_interval' => BillingInterval::Monthly]);
    }

    public function yearly(): static
    {
        return $this->state(fn () => ['billing_interval' => BillingInterval::Yearly]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => PlanStatus::Active]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => PlanStatus::InActive]);
    }

    public function withTrial(int $days = 14): static
    {
        return $this->state(fn () => ['trial_days' => $days]);
    }

    public function withFeature(Feature $feature, bool $enabled = true): static
    {
        return $this->afterCreating(function (Plan $plan) use ($feature, $enabled): void {
            $plan->features()->attach($feature->id, ['enabled' => $enabled]);
        });
    }

    public function withLimit(Limit $limit, int $value = 10): static
    {
        return $this->afterCreating(function (Plan $plan) use ($limit, $value): void {
            $plan->limits()->attach($limit->id, ['value' => $value]);
        });
    }
}
