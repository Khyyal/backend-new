<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Models\Schedule;

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
                'start_time' => Schedule::clockTime($reservation->start_time),
                'end_time' => Schedule::clockTime($reservation->end_time),
            ])->all()),
        ];
    }
}
