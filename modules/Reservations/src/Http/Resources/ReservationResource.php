<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reservations\Models\Reservation;

/**
 * A reservation as its client sees it.
 *
 * @property Reservation $resource
 */
class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reservation = $this->resource;

        return [
            'id' => $reservation->id,
            'status' => $reservation->status->value,
            'source' => $reservation->source->value,
            'center_id' => $reservation->center_id,
            'service' => $this->whenLoaded('service', fn (): array => [
                'id' => $reservation->service->id,
                'type' => $reservation->service->type->value,
                'name' => $reservation->service->getTranslations('name'),
            ]),
            'trainer' => $this->whenLoaded('trainer', fn (): ?array => $reservation->trainer === null ? null : [
                'id' => $reservation->trainer->id,
                'name' => $reservation->trainer->name,
            ]),
            'price_option_id' => $reservation->price_option_id,
            'client_package_id' => $reservation->client_package_id,
            'date' => $reservation->date->toDateString(),
            'end_date' => $reservation->end_date?->toDateString(),
            'start_time' => $reservation->start_time !== null ? substr($reservation->start_time, 0, 5) : null,
            'end_time' => $reservation->end_time !== null ? substr($reservation->end_time, 0, 5) : null,
            'quantity' => $reservation->quantity,
            'unit_price' => (float) $reservation->unit_price,
            'subtotal' => (float) $reservation->subtotal,
            'total_amount' => (float) $reservation->total_amount,
            'lines' => ReservationLineResource::collection($this->whenLoaded('lines')),
            'cancelled_at' => $reservation->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $reservation->cancellation_reason,
            'refund' => $reservation->metadata['refund'] ?? null,
            'created_at' => $reservation->created_at?->toIso8601String(),
        ];
    }
}
