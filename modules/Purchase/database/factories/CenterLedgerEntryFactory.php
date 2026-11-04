<?php

namespace Modules\Purchase\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Purchase\Enums\CenterLedgerEntryType;
use Modules\Purchase\Models\CenterLedgerEntry;

/**
 * @extends Factory<CenterLedgerEntry>
 */
class CenterLedgerEntryFactory extends Factory
{
    protected $model = CenterLedgerEntry::class;

    public function definition(): array
    {
        return [
            'center_id' => CenterFactory::new(),
            'purchase_id' => PurchaseFactory::new(),
            'payment_id' => null,
            'type' => CenterLedgerEntryType::Payment,
            'amount' => fake()->numberBetween(1000, 50000),
            'available_at' => now(),
            'payout_id' => null,
            'idempotency_key' => 'test:'.Str::uuid(),
        ];
    }
}
