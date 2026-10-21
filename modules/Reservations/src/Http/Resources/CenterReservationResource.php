<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;

/**
 * A reservation as center staff see it: adds the client, staff notes and the
 * reschedule history.
 */
class CenterReservationResource extends ReservationResource
{
    public function toArray(Request $request): array
    {
        $reservation = $this->resource;

        return [
            ...parent::toArray($request),
            'client' => $this->whenLoaded('client', fn (): array => [
                'id' => $reservation->client->id,
                'full_name' => $reservation->client->full_name,
                'phone_number' => $reservation->client->phone_number,
            ]),
            'notes' => $reservation->notes,
            'reschedules' => $reservation->metadata['reschedules'] ?? [],
        ];
    }
}
