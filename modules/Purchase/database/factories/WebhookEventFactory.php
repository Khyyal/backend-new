<?php

namespace Modules\Purchase\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Purchase\Models\WebhookEvent;

class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    private static int $seq = 0;

    public function definition(): array
    {
        self::$seq++;

        return [
            'gateway' => fake()->randomElement(['fake', 'moyasar', 'stripe', 'paypal']),
            'external_event_id' => 'evt_'.fake()->bothify('##############').'_'.self::$seq,
            'event_type' => fake()->randomElement([
                'payment.succeeded',
                'payment.failed',
                'payment.cancelled',
                'payment.expired',
                'refund.succeeded',
            ]),
            'payload' => [
                    'id' => fake()->bothify('pi_############'),
                    'amount' => fake()->numberBetween(100, 5000),
                    'currency' => 'SAR',
                    'status' => fake()->randomElement(['succeeded', 'failed', 'pending']),
                ],
            'received_at' => now(),
            'processed_at' => fake()->optional()->boolean(70) ? now() : null,
            'failed_at' => null,
            'attempts' => 1,
        ];
    }

    public function unprocessed(): static
    {
        return $this->state(fn (array $attr) => [
            'processed_at' => null,
            'failed_at' => null,
            'attempts' => 0,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attr) => [
            'processed_at' => null,
            'failed_at' => now(),
            'attempts' => fake()->numberBetween(1, 3),
        ]);
    }
}
