<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Reservations\Enums\ReservationLineKind;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Models\Schedule;

/**
 * A reservation as its client sees it.
 *
 * @property Reservation $resource
 */
class ReservationResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Reservation ID.', example: 311)]
    #[ResponseField('code', 'string', 'Public 6-character reservation code, unique; use it to look the reservation up.', example: 'K7QX2M')]
    #[ResponseField('status', 'string', '`pending` until paid (online), `confirmed`, then `completed` or `no_show`; or `cancelled`.', enum: ReservationStatus::class, example: 'confirmed')]
    #[ResponseField('source', 'string', 'Who booked it: the client, or the center (walk-in).', enum: ReservationSource::class, example: 'client')]
    #[ResponseField('center_id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('service', 'object', 'The service (`id`, `type`, `name` by locale). Present when loaded.', required: false, example: ['id' => 31, 'type' => 'recreation_riding', 'name' => ['ar' => 'ركوب للمبتدئين', 'en' => 'Beginner ride']])]
    #[ResponseField('trainer', 'object', 'Trainer (`id`, `name`) for training; null otherwise. Present when loaded.', required: false, nullable: true, example: ['id' => 6, 'name' => 'Fahad'])]
    #[ResponseField('price_option_id', 'integer', 'Chosen duration option (riding, training); null otherwise.', nullable: true, example: 70)]
    #[ResponseField('price_option', 'object', 'The chosen option (`id`, `name`, `price` in halalas, `quantity`, `unit`); null when none. Present when loaded.', required: false, nullable: true, example: ['id' => 70, 'name' => '1 hour', 'price' => 15000, 'quantity' => 60, 'unit' => 'minute'])]
    #[ResponseField('client_package_id', 'integer', 'Package the session was drawn from; null otherwise.', nullable: true, example: 'No-example')]
    #[ResponseField('client_membership_id', 'integer', 'Membership the free visit was drawn from; null otherwise.', nullable: true, example: 'No-example')]
    #[ResponseField('date', 'string', 'Day, `Y-m-d` (first night for resorts).', example: '2026-06-05')]
    #[ResponseField('end_date', 'string', 'Last day of a resort stay, `Y-m-d`; null otherwise.', nullable: true, example: 'No-example')]
    #[ResponseField('start_time', 'string', 'Start, `HH:MM`; null when not timed.', nullable: true, example: '16:00')]
    #[ResponseField('end_time', 'string', 'End, `HH:MM`; null when not timed.', nullable: true, example: '17:00')]
    #[ResponseField('quantity', 'integer', 'People or tickets.', example: 2)]
    #[ResponseField('unit_price', 'integer', 'Unit price in halalas.', example: 15000)]
    #[ResponseField('subtotal', 'integer', 'Before discounts, in halalas.', example: 30000)]
    #[ResponseField('total_amount', 'integer', 'After discounts, in halalas.', example: 25500)]
    #[ResponseField('lines', 'object[]', 'Tickets and add-ons (events, resorts). Present when loaded.', required: false)]
    #[ResponseField('lines[].id', 'integer', 'Line ID.', example: 1)]
    #[ResponseField('lines[].kind', 'string', '`ticket` or `addon`.', enum: ReservationLineKind::class, example: 'ticket')]
    #[ResponseField('lines[].price_option_id', 'integer', 'Price option.', example: 71)]
    #[ResponseField('lines[].name', 'string', 'Option name.', nullable: true, example: 'Adult ticket')]
    #[ResponseField('lines[].quantity', 'integer', 'Quantity.', example: 2)]
    #[ResponseField('lines[].unit_price', 'integer', 'Unit price in halalas.', example: 5000)]
    #[ResponseField('lines[].subtotal', 'integer', 'Line subtotal in halalas.', example: 10000)]
    #[ResponseField('cancelled_at', 'string', 'Cancellation time (ISO-8601); null otherwise.', nullable: true, example: 'No-example')]
    #[ResponseField('cancellation_reason', 'string', 'Reason given when cancelling.', nullable: true, example: 'No-example')]
    #[ResponseField('refund', 'object', 'Refund of a cancelled paid reservation: `amount` (halalas), `payment_id`, `method`, `status` (`refunded`, or `manual_required` for manual payments). Null otherwise.', nullable: true, example: 'No-example')]
    #[ResponseField('created_at', 'string', 'Booking time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    public function toArray(Request $request): array
    {
        $reservation = $this->resource;

        return [
            'id' => $reservation->id,
            'code' => $reservation->code,
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
            'price_option' => $this->whenLoaded('priceOption', fn (): ?array => $reservation->priceOption === null ? null : [
                'id' => $reservation->priceOption->id,
                'name' => $reservation->priceOption->name,
                'price' => $reservation->priceOption->price,
                'quantity' => $reservation->priceOption->quantity,
                'unit' => $reservation->priceOption->unit->value,
            ]),
            'client_package_id' => $reservation->client_package_id,
            'client_membership_id' => $reservation->client_membership_id,
            'date' => $reservation->date->toDateString(),
            'end_date' => $reservation->end_date?->toDateString(),
            'start_time' => Schedule::clockTime($reservation->start_time),
            'end_time' => Schedule::clockTime($reservation->end_time),
            'quantity' => $reservation->quantity,
            'unit_price' => $reservation->unit_price,
            'subtotal' => $reservation->subtotal,
            'total_amount' => $reservation->total_amount,
            'lines' => ReservationLineResource::collection($this->whenLoaded('lines')),
            'cancelled_at' => $reservation->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $reservation->cancellation_reason,
            'refund' => $reservation->metadata['refund'] ?? null,
            'created_at' => $reservation->created_at?->toIso8601String(),
        ];
    }
}
