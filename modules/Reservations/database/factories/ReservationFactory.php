<?php

namespace Modules\Reservations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Services\Models\Service;

class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->numberBetween(50, 500);

        return [
            'service_id' => ServiceFactory::new(),
            'center_id' => fn (array $attributes) => Service::query()->findOrFail($attributes['service_id'])->center_id,
            'client_id' => ClientFactory::new(),
            'source' => ReservationSource::Client,
            'status' => ReservationStatus::Confirmed,
            'date' => now()->addWeek()->toDateString(),
            'quantity' => 1,
            'unit_price' => $price,
            'subtotal' => $price,
            'total_amount' => $price,
        ];
    }

    public function forService(Service $service): static
    {
        return $this->state([
            'service_id' => $service->getKey(),
            'center_id' => $service->center_id,
        ]);
    }

    public function at(string $date, ?string $startTime = null, ?string $endTime = null): static
    {
        return $this->state([
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    public function status(ReservationStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
