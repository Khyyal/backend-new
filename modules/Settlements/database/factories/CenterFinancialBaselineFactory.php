<?php

namespace Modules\Settlements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Settlements\Models\CenterFinancialBaseline;

/**
 * A pending baseline by default, as the purchases migrator leaves it.
 *
 * @extends Factory<CenterFinancialBaseline>
 */
class CenterFinancialBaselineFactory extends Factory
{
    protected $model = CenterFinancialBaseline::class;

    public function definition(): array
    {
        return [
            'center_id' => CenterFactory::new(),
        ];
    }

    /**
     * Confirmed with a zero balance and nothing migrated, unless overridden.
     */
    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'cutoff_at' => now(),
            'target_balance' => 0,
            'migrated_balance' => 0,
            'migrated_platform_fees' => 0,
            'migrated_gross_sales' => 0,
            'migrated_refunds' => 0,
            'note' => 'Confirmed by finance.',
            'confirmed_at' => now(),
        ]);
    }
}
