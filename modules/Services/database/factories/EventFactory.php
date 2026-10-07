<?php

namespace Modules\Services\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Services\Enums\EventOccurrenceType;
use Modules\Services\Models\Event;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('+1 week', '+1 month');
        $endDate = (clone $startDate)->modify('+'.$this->faker->numberBetween(0, 5).' days');
        $openDate = $this->faker->dateTimeBetween('-1 week', 'now');
        $closeDate = (clone $openDate)->modify('+'.$this->faker->numberBetween(1, 10).' days');

        return [
            'occurrence_type' => $this->faker->randomElement(EventOccurrenceType::cases())->value,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'open_date' => $openDate->format('Y-m-d'),
            'close_date' => $closeDate->format('Y-m-d'),
            'max_tickets_per_day' => $this->faker->numberBetween(1, 200),
        ];
    }
}
