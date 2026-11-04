<?php

namespace Modules\Purchase\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Purchase\Enums\CenterPayoutStatus;
use Modules\Purchase\Models\CenterPayout;

/**
 * @extends Factory<CenterPayout>
 */
class CenterPayoutFactory extends Factory
{
    protected $model = CenterPayout::class;

    public function definition(): array
    {
        return [
            'center_id' => CenterFactory::new(),
            'period_end' => now()->startOfDay(),
            'amount' => fake()->numberBetween(1000, 500000),
            'entries_count' => 1,
            'status' => CenterPayoutStatus::Pending,
            'iban' => 'SA'.fake()->numerify(str_repeat('#', 22)),
            'account_name' => fake()->company(),
            'bank_name' => 'Al Rajhi Bank',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => CenterPayoutStatus::Paid,
            'reference' => fake()->bothify('TRX-#####'),
            'paid_at' => now(),
        ]);
    }
}
