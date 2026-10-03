<?php

namespace Modules\Purchase\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Merchant\Platform;
use Modules\Purchase\Models\Purchase;

class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 50, 5000);
        $discount = fake()->randomFloat(2, 0, 500);
        $tax = fake()->randomFloat(2, 0, 200);
        $total = $subtotal - $discount + $tax;

        $buyer = ClientFactory::new()->create();

        return [
            'buyer_type' => $buyer->getMorphClass(),
            'buyer_id' => $buyer->getKey(),
            'merchant_type' => (new Platform())->getMorphClass(),
            'merchant_id' => 1,
            'source' => fake()->randomElement(PurchaseSource::cases()),
            'status' => fake()->randomElement(PurchaseStatus::cases()),
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total_amount' => max(0, $total),
            'metadata' => fake()->optional()->boolean(50) ? ['notes' => fake()->sentence()] : null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Purchase $purchase) {
            // no-op
        })->afterCreating(function (Purchase $purchase) {
            // no-op
        });
    }

    public function hasItems(int $count = 1): static
    {
        return $this->has(PurchaseItemFactory::new()->count($count), 'items');
    }

    public function hasPayments(int $count = 1): static
    {
        return $this->has(PaymentFactory::new()->count($count), 'payments');
    }
}
