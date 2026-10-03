<?php

namespace Modules\Purchase\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $method = fake()->randomElement(PaymentMethod::cases());

        return [
            'purchase_id' => Purchase::factory(),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'method' => $method,
            'status' => PaymentStatus::Pending,
            'payment_company' => $method === PaymentMethod::Online ? fake()->randomElement(['moyasar', 'stripe', 'paypal']) : null,
            'payment_type' => $method === PaymentMethod::Online ? fake()->randomElement(['card', 'apple_pay', 'stc_pay']) : null,
            'payment_order_id' => $method === PaymentMethod::Online ? fake()->bothify('ORD-########') : null,
            'provider_data' => $method === PaymentMethod::Online ? ['session_id' => fake()->bothify('sess_##########')] : null,
            'metadata' => fake()->optional()->boolean(30) ? ['ip' => fake()->ipv4()] : null,
            'paid_at' => null,
        ];
    }

    public function online(): static
    {
        return $this->state(fn (array $attr) => [
            'method' => PaymentMethod::Online,
            'payment_company' => 'moyasar',
            'payment_type' => 'card',
            'payment_order_id' => fake()->bothify('ORD-########'),
        ]);
    }

    public function cashOnArrival(): static
    {
        return $this->state(fn (array $attr) => [
            'method' => PaymentMethod::CashOnArrival,
            'payment_company' => null,
            'payment_type' => null,
            'payment_order_id' => null,
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attr) => [
            'status' => PaymentStatus::Succeeded,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attr) => [
            'status' => PaymentStatus::Failed,
            'paid_at' => null,
        ]);
    }
}
