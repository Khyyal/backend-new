<?php

namespace Modules\Reservations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reservations\Enums\StayInvoiceKind;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Models\StayInvoice;

/**
 * @extends Factory<StayInvoice>
 */
class StayInvoiceFactory extends Factory
{
    protected $model = StayInvoice::class;

    public function definition(): array
    {
        $price = fake()->numberBetween(1000, 50000);

        return [
            'reservation_id' => ReservationFactory::new(),
            'center_id' => fn (array $attributes) => Reservation::query()->findOrFail($attributes['reservation_id'])->center_id,
            'client_id' => fn (array $attributes) => Reservation::query()->findOrFail($attributes['reservation_id'])->client_id,
            'service_id' => fn (array $attributes) => Reservation::query()->findOrFail($attributes['reservation_id'])->service_id,
            'kind' => StayInvoiceKind::Standalone,
            'date' => now()->toDateString(),
            'name' => fake()->words(2, true),
            'price' => $price,
            'total_amount' => $price,
        ];
    }
}
