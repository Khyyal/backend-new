<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Modules\Reservations\Models\Reservation;

/**
 * A client package with the sessions booked from it.
 */
class CenterClientPackageDetailsResource extends CenterClientPackageResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'reservations' => $this->whenLoaded('reservations', fn (): array => $this->resource->reservations->map(fn (Reservation $reservation): array => [
                'id' => $reservation->id,
                'status' => $reservation->status->value,
                'date' => $reservation->date->toDateString(),
                'start_time' => $reservation->start_time !== null ? substr($reservation->start_time, 0, 5) : null,
                'end_time' => $reservation->end_time !== null ? substr($reservation->end_time, 0, 5) : null,
            ])->all()),
        ];
    }
}
