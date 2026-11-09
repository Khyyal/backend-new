<?php

namespace Modules\Purchase\Http\Resources\Admin;

use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\PurchaseAdjustment;
use Modules\Purchase\Models\PurchaseItem;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\Service;

/**
 * A purchase with its line items, adjustments and payments. Each item's `details` describe
 * what was booked (a reservation or a client package), or are `null` for any
 * other purchasable.
 */
class PurchaseDetailsResource extends PurchaseResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'integer', 'Purchase ID.', example: 5521)]
    #[ResponseField('status', 'string', 'Purchase status.', enum: PurchaseStatus::class, example: 'confirmed')]
    #[ResponseField('source', 'string', 'Who created it.', enum: PurchaseSource::class, example: 'client')]
    #[ResponseField('channel', 'string', 'Where it was made; null when unknown.', nullable: true, enum: PurchaseChannel::class, example: 'khyyal_app')]
    #[ResponseField('payment_method', 'string', 'Method of the latest payment; null without payments.', nullable: true, enum: PaymentMethod::class, example: 'online')]
    #[ResponseField('payment_status', 'string', 'Status of the latest payment; null without payments.', nullable: true, enum: PaymentStatus::class, example: 'succeeded')]
    #[ResponseField('client', 'object', 'The buyer (`id`, `full_name`, `phone_number`); null when deleted.', nullable: true, example: ['id' => 77, 'full_name' => 'Noura Al-Qahtani', 'phone_number' => '+966500000003'])]
    #[ResponseField('center', 'object', 'The center sold from (`id`, `name`); null when deleted.', nullable: true, example: ['id' => 8, 'name' => 'Al Fursan Stables'])]
    #[ResponseField('items_count', 'integer', 'Line items.', example: 1)]
    #[ResponseField('subtotal', 'integer', 'Before discounts, in halalas.', example: 30000)]
    #[ResponseField('discount_amount', 'integer', 'Discount in halalas.', example: 4500)]
    #[ResponseField('tax_amount', 'integer', 'VAT in halalas.', example: 3825)]
    #[ResponseField('total_amount', 'integer', 'Charged, in halalas.', example: 29325)]
    #[ResponseField('platform_fee_amount', 'integer', 'Platform fee owed by the center, in halalas.', example: 1466)]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('items', 'object[]', 'Line items with `id`, `name`, `purchasable_type` (`Reservation`, `ClientPackage`, `ClientMembership`), `purchasable_id`, `quantity`, `unit_price`, `subtotal`, `discount_amount`, `tax_amount`, `total_amount` (halalas) and `details` (reservation: status, service, trainer, price_option, date, end_date, start_time, end_time, quantity; client package: status, service, price_option, total_sessions, remaining_sessions, expires_at; otherwise null).', example: [['id' => 9001, 'name' => 'Beginner ride - 60 min', 'purchasable_type' => 'Reservation', 'purchasable_id' => 311, 'quantity' => 2, 'unit_price' => 15000, 'subtotal' => 30000, 'discount_amount' => 4500, 'tax_amount' => 3825, 'total_amount' => 29325, 'details' => ['status' => 'confirmed', 'date' => '2026-06-05', 'start_time' => '16:00', 'end_time' => '17:00', 'quantity' => 2]]])]
    #[ResponseField('coupon_code', 'string', 'Coupon applied; null when none.', nullable: true, example: 'SUMMER25')]
    #[ResponseField('adjustments', 'object[]', 'Discounts: `type` (`promotion`/`coupon`), `purchase_item_id`, `promotion_id`, `code`, `label`, `amount`, `funded_by`, `center_share_amount`.', example: [['type' => 'coupon', 'purchase_item_id' => 9001, 'promotion_id' => 12, 'code' => 'SUMMER25', 'label' => '15% off rides', 'amount' => 4500, 'funded_by' => 'center', 'center_share_amount' => 4500]])]
    #[ResponseField('payments', 'object[]', 'Payments: `id`, `method`, `status`, `payment_company`, `amount`, `refunded_amount`, `paid_at`, `refunded_at`.', example: [['id' => 801, 'method' => 'online', 'status' => 'succeeded', 'payment_company' => 'moyasar', 'amount' => 29325, 'refunded_amount' => 0, 'paid_at' => '2026-06-01T10:00:00+00:00', 'refunded_at' => null]])]
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'items' => $this->whenLoaded('items', fn (): array => $this->resource->items->map(fn (PurchaseItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'purchasable_type' => class_basename($item->purchasable_type),
                'purchasable_id' => $item->purchasable_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
                'discount_amount' => $item->discount_amount,
                'tax_amount' => $item->tax_amount,
                'total_amount' => $item->total_amount,
                'details' => $this->details($item),
            ])->all()),
            'coupon_code' => $this->resource->coupon_code,
            'adjustments' => $this->whenLoaded('adjustments', fn (): array => $this->resource->adjustments->map(fn (PurchaseAdjustment $adjustment): array => [
                'type' => $adjustment->type,
                'purchase_item_id' => $adjustment->purchase_item_id,
                'promotion_id' => $adjustment->promotion_id,
                'code' => $adjustment->code,
                'label' => $adjustment->label,
                'amount' => $adjustment->amount,
                'funded_by' => $adjustment->funded_by,
                'center_share_amount' => $adjustment->center_share_amount,
            ])->all()),
            'payments' => $this->whenLoaded('payments', fn (): array => $this->resource->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'method' => $payment->method->value,
                'status' => $payment->status->value,
                'payment_company' => $payment->payment_company,
                'amount' => $payment->amount,
                'refunded_amount' => $payment->refunded_amount,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'refunded_at' => $payment->refunded_at?->toIso8601String(),
            ])->all()),
        ];
    }

    /**
     * What the item booked, read from its eager-loaded purchasable.
     *
     * @return array<string, mixed>|null
     */
    private function details(PurchaseItem $item): ?array
    {
        $purchasable = $item->relationLoaded('purchasable') ? $item->purchasable : null;

        return match (true) {
            $purchasable instanceof Reservation => [
                'status' => $purchasable->status->value,
                'service' => $this->service($purchasable->service),
                'trainer' => $purchasable->trainer === null ? null : [
                    'id' => $purchasable->trainer->id,
                    'name' => $purchasable->trainer->name,
                ],
                'price_option' => $this->priceOption($purchasable->priceOption),
                'date' => $purchasable->date->toDateString(),
                'end_date' => $purchasable->end_date?->toDateString(),
                'start_time' => Schedule::clockTime($purchasable->start_time),
                'end_time' => Schedule::clockTime($purchasable->end_time),
                'quantity' => $purchasable->quantity,
            ],
            $purchasable instanceof ClientPackage => [
                'status' => $purchasable->status->value,
                'service' => $this->service($purchasable->service),
                'price_option' => $this->priceOption($purchasable->priceOption),
                'total_sessions' => $purchasable->total_sessions,
                'remaining_sessions' => $purchasable->remaining_sessions,
                'expires_at' => $purchasable->expires_at?->toIso8601String(),
            ],
            default => null,
        };
    }

    /**
     * @return array{id: int, type: string, name: array<string, string>}|null
     */
    private function service(?Service $service): ?array
    {
        return $service === null ? null : [
            'id' => $service->id,
            'type' => $service->type->value,
            'name' => $service->getTranslations('name'),
        ];
    }

    /**
     * @return array{id: int, name: string, quantity: int, unit: string}|null
     */
    private function priceOption(?PriceOption $priceOption): ?array
    {
        return $priceOption === null ? null : [
            'id' => $priceOption->id,
            'name' => $priceOption->name,
            'quantity' => $priceOption->quantity,
            'unit' => $priceOption->unit->value,
        ];
    }
}
