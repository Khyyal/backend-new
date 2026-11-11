<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Models\Schedule;

/**
 * A client package with the sessions booked from it.
 */
class CenterClientPackageDetailsResource extends CenterClientPackageResource
{
    #[ResponseField('id', 'integer', 'Client package ID; book sessions with `POST /api/v1/clients/packages/{package}/reservations`.', example: 41)]
    #[ResponseField('status', 'string', '`pending` until paid, then `active`, `exhausted`, `expired` or `cancelled`.', enum: ClientPackageStatus::class, example: 'active')]
    #[ResponseField('service', 'object', 'The service (`id`, `type`, `name` by locale). Present when loaded.', required: false, example: ['id' => 31, 'type' => 'training', 'name' => ['ar' => 'تدريب خاص', 'en' => 'Private training']])]
    #[ResponseField('service_package_id', 'integer', 'The package bought.', example: 5)]
    #[ResponseField('duration_minutes', 'integer', 'Session length in minutes. Present when loaded.', required: false, nullable: true, example: 60)]
    #[ResponseField('total_sessions', 'integer', 'Sessions bought.', example: 8)]
    #[ResponseField('remaining_sessions', 'integer', 'Sessions left.', example: 5)]
    #[ResponseField('price', 'integer', 'Price paid in halalas.', example: 100000)]
    #[ResponseField('expires_at', 'string', 'Expiry (ISO-8601); null until paid.', nullable: true, example: '2026-09-01T00:00:00+00:00')]
    #[ResponseField('is_usable', 'boolean', 'Active, not expired and with sessions left.', example: true)]
    #[ResponseField('knight_name', 'string', 'Name the client gave the package; null until set.', nullable: true, example: 'Thunder')]
    #[ResponseField('controlled', 'boolean', 'Whether the center controls this package; set by the center.', example: false)]
    #[ResponseField('created_at', 'string', 'Purchase time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('purchase', 'object', 'Short info of the purchase that sold it (`PurchaseSummaryResource`); null when it was not sold through a purchase. Present when loaded.', required: false, nullable: true)]
    #[ResponseField('purchase.id', 'integer', 'Purchase ID.', example: 5521)]
    #[ResponseField('purchase.code', 'string', 'Public 6-character purchase code.', example: 'K7QX2M')]
    #[ResponseField('purchase.status', 'string', 'Purchase status.', example: 'confirmed')]
    #[ResponseField('purchase.channel', 'string', 'Where it was bought; null when unknown.', nullable: true, example: 'khyyal_app')]
    #[ResponseField('purchase.total_amount', 'integer', 'Charged for the whole purchase, in halalas.', example: 25500)]
    #[ResponseField('purchase.discount_amount', 'integer', 'Discount in halalas.', example: 4500)]
    #[ResponseField('purchase.coupon_code', 'string', 'Applied coupon.', nullable: true, example: 'SUMMER25')]
    #[ResponseField('purchase.payment_method', 'string', 'Method of the latest payment; null without payments.', nullable: true, example: 'online')]
    #[ResponseField('purchase.payment_status', 'string', 'Status of the latest payment; null without payments.', nullable: true, example: 'succeeded')]
    #[ResponseField('purchase.created_at', 'string', 'Purchase time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('purchase.client', 'object', 'The buyer; null when deleted.', nullable: true)]
    #[ResponseField('purchase.client.id', 'integer', 'Client ID.', example: 77)]
    #[ResponseField('purchase.client.full_name', 'string', 'Client full name.', example: 'Noura Al-Qahtani')]
    #[ResponseField('purchase.client.phone_number', 'string', 'Client phone.', example: '+966500000003')]
    #[ResponseField('purchase.center', 'object', 'The center sold by; null when deleted.', nullable: true)]
    #[ResponseField('purchase.center.id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('purchase.center.name', 'string', 'Center name.', example: 'Al Fursan Stables')]
    #[ResponseField('purchase.center.city', 'object', 'City (`id`, `name`, `radius`); null when not loaded.', nullable: true)]
    #[ResponseField('purchase.center.city.id', 'integer', 'City ID.', example: 1)]
    #[ResponseField('purchase.center.city.name', 'string', 'City name in the request locale.', example: 'Riyadh')]
    #[ResponseField('purchase.center.city.radius', 'integer', 'Coverage radius in km.', example: 40)]
    #[ResponseField('purchase.center.address', 'string', 'Street address.', nullable: true, example: 'King Fahd Rd, Al Olaya')]
    #[ResponseField('purchase.center.lat', 'string', 'Latitude.', nullable: true, example: '24.7136000')]
    #[ResponseField('purchase.center.lng', 'string', 'Longitude.', nullable: true, example: '46.6753000')]
    #[ResponseField('purchase.center.contact_phone', 'string', 'Public contact phone.', example: '+966500000001')]
    #[ResponseField('client', 'object', 'The client (`id`, `full_name`, `phone_number`). Present when loaded.', required: false, example: ['id' => 77, 'full_name' => 'Noura Al-Qahtani', 'phone_number' => '+966500000003'])]
    #[ResponseField('reservations', 'object[]', 'Sessions booked from it, by date: `id`, `status`, `date`, `start_time`, `end_time`.', example: [['id' => 311, 'status' => 'completed', 'date' => '2026-06-05', 'start_time' => '16:00', 'end_time' => '17:00']])]
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
