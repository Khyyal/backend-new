<?php

namespace Modules\Services\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Services\Models\Trainer;

/**
 * Client-facing shape of a trainer of a training service.
 *
 * @property Trainer $resource
 */
class TrainerResource extends JsonResource
{
    /**
     * @var array<int, array{date: string, available: bool, hours: array<int, array<string, mixed>>}>|null
     */
    private ?array $availability = null;

    /**
     * @param  array<int, array{date: string, available: bool, hours: array<int, array<string, mixed>>}>|null  $availability
     */
    public function withAvailability(?array $availability): static
    {
        $this->availability = $availability;

        return $this;
    }

    #[ResponseField('id', 'integer', 'Trainer ID.', example: 6)]
    #[ResponseField('name', 'string', 'Trainer name.', example: 'Fahad')]
    #[ResponseField('availability', 'object[]', 'Today and the next 14 days. A day is available when any of its slots is.')]
    #[ResponseField('availability[].date', 'string', 'Day, `Y-m-d`.', example: '2026-06-05')]
    #[ResponseField('availability[].available', 'boolean', 'Any slot bookable.', example: true)]
    #[ResponseField('availability[].hours', 'object[]', 'One-hour slots of the day\'s schedule windows. A slot is available when it has not started, is not blocked, has tickets left and bookings are open.')]
    #[ResponseField('availability[].hours[].hour', 'string', 'Slot start, `HH:MM`.', example: '16:00')]
    #[ResponseField('availability[].hours[].available', 'boolean', 'Slot bookable.', example: true)]
    #[ResponseField('availability[].hours[].available_tickets', 'integer', 'Tickets left; null without an hourly limit.', nullable: true, example: 3)]
    #[ResponseField('availability[].hours[].max_tickets', 'integer', 'Hourly limit; null without one.', nullable: true, example: 5)]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'availability' => $this->availability,
        ];
    }
}
