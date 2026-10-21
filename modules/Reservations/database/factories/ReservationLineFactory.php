<?php

namespace Modules\Reservations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reservations\Enums\ReservationLineKind;
use Modules\Reservations\Models\ReservationLine;
use Modules\Services\Database\Factories\PriceOptionFactory;

class ReservationLineFactory extends Factory
{
    protected $model = ReservationLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->numberBetween(10, 200);

        return [
            'reservation_id' => ReservationFactory::new(),
            'price_option_id' => PriceOptionFactory::new(),
            'kind' => ReservationLineKind::Ticket,
            'quantity' => 1,
            'unit_price' => $price,
            'subtotal' => $price,
        ];
    }
}
