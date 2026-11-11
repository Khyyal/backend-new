<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Reservations\Enums\ClientMembershipStatus;

/**
 * A client membership as center staff see it: adds the owner.
 */
class CenterClientMembershipResource extends ClientMembershipResource
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
    public function toArray(Request $request): array
    {
        $membership = $this->resource;

        return [
            ...parent::toArray($request),
            'client' => $this->whenLoaded('client', fn (): array => [
                'id' => $membership->client->id,
                'full_name' => $membership->client->full_name,
                'phone_number' => $membership->client->phone_number,
            ]),
        ];
    }
}
