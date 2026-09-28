<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Models\Center;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $startsAt = now()->subDays(fake()->numberBetween(0, 30));
        $trialDays = fake()->numberBetween(0, 14);
        $trialEndsAt = $trialDays > 0 ? $startsAt->copy()->addDays($trialDays) : null;
        $endsAt = $startsAt->copy()->addMonthNoOverflow();

        return [
            'subscribable_type' => 'center',
            'subscribable_id' => CenterFactory::new(),
            'plan_id' => Plan::factory(),
            'status' => fake()->randomElement([
                SubscriptionStatus::Trialing,
                SubscriptionStatus::Active,
                SubscriptionStatus::Canceled,
                SubscriptionStatus::Expired,
            ]),
            'trial_ends_at' => $trialEndsAt,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'canceled_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'canceled_at' => null,
        ]);
    }

    public function trialing(): static
    {
        return $this->state(function (array $attr): array {
            $trialEnds = now()->addDays(7);

            return [
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => $trialEnds,
                'canceled_at' => null,
            ];
        });
    }

    public function canceled(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => now()->subDays(3),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Expired,
            'canceled_at' => null,
        ]);
    }

    public function trialEndsAt(\DateTimeInterface $when): static
    {
        return $this->state(fn () => ['trial_ends_at' => $when]);
    }

    public function endsAtSoon(): static
    {
        return $this->state(fn () => ['ends_at' => now()->addDays(3)]);
    }

    public function endsAtPast(): static
    {
        return $this->state(fn () => ['ends_at' => now()->subDays(5)]);
    }

    public function canceledDaysAgo(int $days): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => now()->subDays($days),
        ]);
    }

    public function forCenter(Center $center): static
    {
        return $this->state(fn () => [
            'subscribable_type' => 'center',
            'subscribable_id' => $center->id,
        ]);
    }
}
