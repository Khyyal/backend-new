<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Reservations\Enums\ReservationLineKind;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Enums\ReservationStatus;

/**
 * A reservation as center staff see it: adds the client, staff notes and the
 * reschedule history.
 */
class CenterReservationResource extends ReservationResource
{
    #[ResponseField('id', 'integer', 'Reservation ID.', example: 311)]
    #[ResponseField('status', 'string', '`pending` until paid (online), `confirmed`, then `completed` or `no_show`; or `cancelled`.', enum: ReservationStatus::class, example: 'confirmed')]
    #[ResponseField('source', 'string', 'Who booked it: the client, or the center (walk-in).', enum: ReservationSource::class, example: 'client')]
    #[ResponseField('center_id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('service', 'object', 'The service (`id`, `type`, `name` by locale). Present when loaded.', required: false, example: ['id' => 31, 'type' => 'recreation_riding', 'name' => ['ar' => 'ركوب للمبتدئين', 'en' => 'Beginner ride']])]
    #[ResponseField('trainer', 'object', 'Trainer (`id`, `name`) for training; null otherwise. Present when loaded.', required: false, nullable: true, example: ['id' => 6, 'name' => 'Fahad'])]
    #[ResponseField('price_option_id', 'integer', 'Chosen duration option (riding, training); null otherwise.', nullable: true, example: 70)]
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
    #[ResponseField('purchase', 'object', 'Short info of the purchase that sold it (`PurchaseSummaryResource`); null when it was not sold through a purchase. Present when loaded.', required: false, nullable: true)]
    #[ResponseField('client', 'object', 'The client (`id`, `full_name`, `phone_number`). Present when loaded.', required: false, example: ['id' => 77, 'full_name' => 'Noura Al-Qahtani', 'phone_number' => '+966500000003'])]
    #[ResponseField('notes', 'string', 'Staff notes (walk-ins).', nullable: true, example: 'Prefers a calm horse.')]
    #[ResponseField('reschedules', 'object[]', 'Previous slots, oldest first: `date`, `end_date`, `start_time`, `end_time`, `trainer_id`, `rescheduled_at`.', example: [])]
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
