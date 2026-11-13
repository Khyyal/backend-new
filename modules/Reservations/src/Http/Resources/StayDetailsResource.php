<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Reservations\Http\Resources\Concerns\DescribesHorse;
use Modules\Reservations\Models\Reservation;

/**
 * A stabling stay with its horse and the invoices billed during it. Every
 * `CenterReservationResource` field is kept.
 *
 * @property Reservation $resource
 */
class StayDetailsResource extends CenterReservationResource
{
    use DescribesHorse;

    #[ResponseField('horse', 'object', 'The horse (`id`, `name`, `image`, `room`, `client` with `id`/`first_name`/`last_name`/`phone_number`, `trainers` with `id`/`name`).', nullable: true, example: ['id' => 5, 'name' => 'Najm', 'image' => null, 'room' => 12, 'client' => ['id' => 77, 'first_name' => 'Noura', 'last_name' => 'Al-Qahtani', 'phone_number' => '+966500000003'], 'trainers' => [['id' => 6, 'name' => 'Fahad']]])]
    #[ResponseField('planned_end_date', 'string', 'The end date before the stay was ended early, `Y-m-d`; null otherwise.', nullable: true, example: 'No-example')]
    #[ResponseField('unpaid_operations_count', 'integer', 'The stay and its invoices whose purchase is still pending.', example: 1)]
    #[ResponseField('invoices', 'object[]', 'Invoices billed during the stay, newest first (`StayInvoiceResource`).')]
    public function toArray(Request $request): array
    {
        $stay = $this->resource;
        $horse = $stay->horse;

        return [
            ...parent::toArray($request),
            'horse' => $horse === null ? null : [
                ...$this->horseSummary($horse),
                'client' => $this->horseClient($horse->client),
                'trainers' => $this->horseTrainers($horse),
            ],
            'planned_end_date' => $stay->metadata['planned_end_date'] ?? null,
            'unpaid_operations_count' => (int) $stay->unpaid_stay_count + (int) $stay->unpaid_invoices_count,
            'invoices' => StayInvoiceResource::collection($stay->invoices),
        ];
    }
}
