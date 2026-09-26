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
        $unitPrice = fake()->randomFloat(2, 10, 2000);
        $quantity = fake()->numberBetween(1, 5);
        $subtotal = $unitPrice * $quantity;
        $discount = fake()->randomFloat(2, 0, $subtotal * 0.2);
        $tax = fake()->randomFloat(2, 0, $subtotal * 0.15);
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
