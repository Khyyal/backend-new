<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Purchase;
use Modules\Support\Http\Resources\CityResource;

/**
 * Short purchase info embedded in reservations, client packages and client memberships. Amounts are halalas;
 * `payment_method` and `payment_status` come from the latest payment.
 *
 * @property Purchase $resource
 */
class PurchaseSummaryResource extends JsonResource
{
    /**
     * Relations to eager load on a purchasable (reservation, client package, client membership) to embed its purchase.
     *
     * @var list<string>
     */
    public const array RELATIONS = [
        'purchaseItem.purchase.latestPayment',
        'purchaseItem.purchase.buyer',
        'purchaseItem.purchase.merchant.city',
    ];

    /**
     * The summary of the purchase that sold a model: omitted when the relation is not loaded, null when the model
     * was not sold through a purchase.
     */
    public static function forModel(Model $model): self|MissingValue|null
    {
        if (! $model->relationLoaded('purchaseItem')) {
            return new MissingValue;
        }

        $purchase = $model->getRelation('purchaseItem')?->purchase;

        return $purchase === null ? null : new self($purchase);
    }

    #[ResponseField('id', 'integer', 'Purchase ID.', example: 5521)]
    #[ResponseField('code', 'string', 'Public 6-character purchase code.', example: 'K7QX2M')]
    #[ResponseField('status', 'string', 'Purchase status.', enum: PurchaseStatus::class, example: 'confirmed')]
    #[ResponseField('channel', 'string', 'Where it was bought; null when unknown.', nullable: true, enum: PurchaseChannel::class, example: 'khyyal_app')]
    #[ResponseField('total_amount', 'integer', 'Charged for the whole purchase, in halalas.', example: 25500)]
    #[ResponseField('discount_amount', 'integer', 'Discount in halalas.', example: 4500)]
    #[ResponseField('coupon_code', 'string', 'Applied coupon.', nullable: true, example: 'SUMMER25')]
    #[ResponseField('payment_method', 'string', 'Method of the latest payment; null without payments.', nullable: true, enum: PaymentMethod::class, example: 'online')]
    #[ResponseField('payment_status', 'string', 'Status of the latest payment; null without payments.', nullable: true, enum: PaymentStatus::class, example: 'succeeded')]
    #[ResponseField('created_at', 'string', 'Purchase time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('client', 'object', 'The buyer (`id`, `full_name`, `phone_number`); null when deleted.', nullable: true, example: ['id' => 77, 'full_name' => 'Noura Al-Qahtani', 'phone_number' => '+966500000003'])]
    #[ResponseField('center', 'object', 'The center sold by (`id`, `name`, `city` with `id`/`name`/`radius`, `address`, `lat`, `lng`, `contact_phone`); null when deleted.', nullable: true, example: ['id' => 8, 'name' => 'Al Fursan Stables', 'city' => ['id' => 1, 'name' => 'Riyadh', 'radius' => 40], 'address' => 'King Fahd Rd, Al Olaya', 'lat' => '24.7136000', 'lng' => '46.6753000', 'contact_phone' => '+966500000001'])]
    public function toArray(Request $request): array
    {
        $purchase = $this->resource;
        $client = $purchase->buyer instanceof Client ? $purchase->buyer : null;
        $center = $purchase->merchant instanceof Center ? $purchase->merchant : null;

        return [
            'id' => $purchase->id,
            'code' => $purchase->code,
            'status' => $purchase->status->value,
            'channel' => $purchase->channel?->value,
            'total_amount' => $purchase->total_amount,
            'discount_amount' => $purchase->discount_amount,
            'coupon_code' => $purchase->coupon_code,
            'payment_method' => $purchase->latestPayment?->method->value,
            'payment_status' => $purchase->latestPayment?->status->value,
            'created_at' => $purchase->created_at?->toIso8601String(),
            'client' => $client === null ? null : [
                'id' => $client->id,
                'full_name' => $client->full_name,
                'phone_number' => $client->phone_number,
            ],
            'center' => $center === null ? null : [
                'id' => $center->id,
                'name' => $center->name,
                'city' => $center->relationLoaded('city') && $center->city !== null ? CityResource::make($center->city) : null,
                'address' => $center->address,
                'lat' => $center->lat,
                'lng' => $center->lng,
                'contact_phone' => $center->contact_phone,
            ],
        ];
    }
}
