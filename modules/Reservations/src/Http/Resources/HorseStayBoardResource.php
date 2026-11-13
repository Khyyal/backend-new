<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Centers\Models\Horse;
use Modules\Reservations\Http\Resources\Concerns\DescribesHorse;
use Modules\Reservations\Models\Reservation;

/**
 * One row of the horses board: an active stabling stay or a horse without one.
 *
 * @property Reservation|Horse $resource
 */
class HorseStayBoardResource extends JsonResource
{
    use DescribesHorse;

    #[ResponseField('type', 'string', '`stay` (an active stabling stay) or `horse` (a horse without an active stay); decides the other fields.', enum: ['stay', 'horse'], example: 'stay')]
    #[ResponseField('id', 'integer', 'Reservation ID for a stay, horse ID for a horse.', example: 412)]
    #[ResponseField('status', 'string', 'Stay only: reservation status (`confirmed`).', required: false, example: 'confirmed')]
    #[ResponseField('end_date', 'string', 'Stay only: last day, `Y-m-d`; null for an open-ended stay.', required: false, nullable: true, example: '2026-12-31')]
    #[ResponseField('horse', 'object', 'Stay only: the horse (`id`, `name`, `image`, `room`, `client`).', required: false)]
    #[ResponseField('service', 'object', 'Stay only: the stabling service (`id`, `name` by locale).', required: false, example: ['id' => 31, 'name' => ['ar' => 'إسطبل شهري', 'en' => 'Monthly stabling']])]
    #[ResponseField('unpaid_operations_count', 'integer', 'Stay only: the stay and its invoices whose purchase is still pending.', required: false, example: 2)]
    #[ResponseField('name', 'string', 'Horse only: horse name.', required: false, example: 'Najm')]
    #[ResponseField('image', 'object', 'Horse only: image (`MediaResource`); null when none.', required: false, nullable: true)]
    #[ResponseField('room', 'integer', 'Horse only: room number.', required: false, example: 12)]
    #[ResponseField('client', 'object', 'Horse only: owner (`id`, `first_name`, `last_name`, `phone_number`); null for center-owned horses.', required: false, nullable: true)]
    #[ResponseField('trainers', 'object[]', 'Horse only: trainers (`id`, `name`).', required: false, example: [['id' => 6, 'name' => 'Fahad']])]
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        if ($item instanceof Reservation) {
            return [
                'type' => 'stay',
                'id' => $item->id,
                'status' => $item->status->value,
                'end_date' => $item->end_date?->toDateString(),
                'horse' => $item->horse === null ? null : [
                    ...$this->horseSummary($item->horse),
                    'client' => $this->horseClient($item->horse->client),
                ],
                'service' => [
                    'id' => $item->service->id,
                    'name' => $item->service->getTranslations('name'),
                ],
                'unpaid_operations_count' => (int) $item->unpaid_stay_count + (int) $item->unpaid_invoices_count,
            ];
        }

        return [
            'type' => 'horse',
            ...$this->horseSummary($item),
            'client' => $this->horseClient($item->client),
            'trainers' => $this->horseTrainers($item),
        ];
    }
}
