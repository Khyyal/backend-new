<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Models\Schedule;

/**
 * A client membership with the free visits booked from it.
 */
class CenterClientMembershipDetailsResource extends CenterClientMembershipResource
{
    #[ResponseField('id', 'integer', 'Client membership ID.', example: 9)]
    #[ResponseField('status', 'string', '`pending` until paid, then `active`, `expired` or `cancelled`.', enum: ClientMembershipStatus::class, example: 'active')]
    #[ResponseField('service', 'object', 'The membership service (`id`, `name` by locale, `color`). Present when loaded.', required: false, example: ['id' => 40, 'name' => ['ar' => 'عضوية ذهبية', 'en' => 'Gold membership'], 'color' => '#D4AF37'])]
    #[ResponseField('membership_id', 'integer', 'The membership bought.', example: 3)]
    #[ResponseField('price', 'integer', 'Price paid in halalas.', example: 250000)]
    #[ResponseField('duration', 'integer', 'Validity in months.', example: 6)]
    #[ResponseField('visit_service', 'object', 'Visit service with free entries (`id`, `name` by locale); null when none. Present when loaded.', required: false, nullable: true, example: ['id' => 33, 'name' => ['ar' => 'زيارة', 'en' => 'Visit']])]
    #[ResponseField('max_visits_per_day', 'integer', 'Free visits per day; null without a visit service.', nullable: true, example: 1)]
    #[ResponseField('visits_left_today', 'integer', 'Free visits left today. Only when the membership grants visits and usage was computed.', required: false, example: 1)]
    #[ResponseField('packages', 'object[]', 'Included packages (`ClientPackageResource`). Present when loaded.', required: false)]
    #[ResponseField('expires_at', 'string', 'Expiry (ISO-8601); null until paid.', nullable: true, example: '2026-12-01T00:00:00+00:00')]
    #[ResponseField('is_usable', 'boolean', 'Active and not expired.', example: true)]
    #[ResponseField('created_at', 'string', 'Purchase time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('client', 'object', 'The client (`id`, `full_name`, `phone_number`). Present when loaded.', required: false, example: ['id' => 77, 'full_name' => 'Noura Al-Qahtani', 'phone_number' => '+966500000003'])]
    #[ResponseField('reservations', 'object[]', 'Visits booked from it, by date: `id`, `status`, `date`, `start_time`, `end_time`, `quantity`.', example: [['id' => 320, 'status' => 'confirmed', 'date' => '2026-06-06', 'start_time' => null, 'end_time' => null, 'quantity' => 1]])]
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
                'quantity' => $reservation->quantity,
            ])->all()),
        ];
    }
}
