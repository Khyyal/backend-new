<?php

namespace Modules\Purchase\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Models\PurchaseItem;

class PurchaseItemFactory extends Factory
{
    protected $model = PurchaseItem::class;

    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(1000, 200000);
        $quantity = fake()->numberBetween(1, 5);
        $subtotal = $unitPrice * $quantity;
        $discount = fake()->numberBetween(0, intdiv($subtotal, 5));
        $tax = fake()->numberBetween(0, intdiv($subtotal * 15, 100));
        $total = $subtotal - $discount + $tax;

        return [
            'purchase_id' => Purchase::factory(),
            'purchasable_type' => 'fake_service',
            'purchasable_id' => fake()->numberBetween(1, 100),
            'name' => fake()->words(3, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total_amount' => max(0, $total),
            'metadata' => fake()->optional()->boolean(30) ? ['sku' => fake()->bothify('???-####')] : null,
        ];
    }
}
