<?php

namespace Modules\Centers\Http\Resources\Center;

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
use Modules\Reservations\Models\StayInvoice;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\Service;

/**
 * A purchase with its line items and payments. Each item's `details` describe
 * what was booked (a reservation or a client package), or are `null` for any
 * other purchasable.
 */
class PurchaseDetailsResource extends PurchaseResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'integer', 'Purchase ID.', example: 5521)]
    #[ResponseField('code', 'string', 'Public 6-character purchase code, unique; use it to look the purchase up.', example: 'K7QX2M')]
    #[ResponseField('status', 'string', 'Purchase status.', enum: PurchaseStatus::class, example: 'confirmed')]
    #[ResponseField('source', 'string', 'Who created it.', enum: PurchaseSource::class, example: 'client')]
    #[ResponseField('channel', 'string', 'Where it was made; null when unknown.', nullable: true, enum: PurchaseChannel::class, example: 'khyyal_app')]
    #[ResponseField('payment_method', 'string', 'Method of the latest payment; null without payments.', nullable: true, enum: PaymentMethod::class, example: 'online')]
    #[ResponseField('payment_status', 'string', 'Status of the latest payment; null without payments.', nullable: true, enum: PaymentStatus::class, example: 'succeeded')]
    #[ResponseField('client', 'object', 'The buyer; null when the client was deleted.', nullable: true)]
    #[ResponseField('client.id', 'integer', 'Client ID.', example: 77)]
    #[ResponseField('client.full_name', 'string', 'Client full name.', example: 'Noura Al-Qahtani')]
    #[ResponseField('client.phone_number', 'string', 'Client phone.', example: '+966500000003')]
    #[ResponseField('items_count', 'integer', 'Number of line items.', example: 1)]
    #[ResponseField('subtotal', 'integer', 'Sum of line subtotals in halalas. Only with `purchases.view_prices` (system users always).', required: false, example: 30000)]
    #[ResponseField('discount_amount', 'integer', 'Total discount in halalas. Only with `purchases.view_prices`.', required: false, example: 4500)]
    #[ResponseField('tax_amount', 'integer', 'VAT in halalas. Only with `purchases.view_prices`.', required: false, example: 3825)]
    #[ResponseField('total_amount', 'integer', 'Amount charged in halalas. Only with `purchases.view_prices`.', required: false, example: 29325)]
    #[ResponseField('platform_fee_amount', 'integer', 'Khyyal platform fee owed on it, in halalas. Only with `purchases.view_prices`.', required: false, example: 1466)]
    #[ResponseField('center_amount', 'integer', 'Net amount credited to the center ledger, in halalas. Only with `purchases.view_prices`.', required: false, example: 27859)]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('items', 'object[]', 'Line items.')]
    #[ResponseField('items[].id', 'integer', 'Item ID.', example: 9001)]
    #[ResponseField('items[].name', 'string', 'Snapshot of the item name.', example: 'Beginner ride - 60 min')]
    #[ResponseField('items[].purchasable_type', 'string', 'What was bought (morph alias): `reservation`, `client_package`, `client_membership`, `stay_invoice` or `subscription`.', example: 'reservation')]
    #[ResponseField('items[].purchasable_id', 'integer', 'ID of the bought record.', example: 311)]
    #[ResponseField('items[].quantity', 'integer', 'Quantity.', example: 2)]
    #[ResponseField('items[].unit_price', 'integer', 'Unit price in halalas. Only with `purchases.view_prices`.', required: false, example: 15000)]
    #[ResponseField('items[].subtotal', 'integer', 'Line subtotal in halalas. Only with `purchases.view_prices`.', required: false, example: 30000)]
    #[ResponseField('items[].discount_amount', 'integer', 'Line discount in halalas. Only with `purchases.view_prices`.', required: false, example: 4500)]
    #[ResponseField('items[].tax_amount', 'integer', 'Line VAT in halalas. Only with `purchases.view_prices`.', required: false, example: 3825)]
    #[ResponseField('items[].total_amount', 'integer', 'Line total in halalas. Only with `purchases.view_prices`.', required: false, example: 29325)]
    #[ResponseField('items[].details', 'object', 'What was booked. Reservation: status, service, trainer, price_option, date, end_date, start_time, end_time, quantity. Client package: status, service, price_option, total_sessions, remaining_sessions, expires_at. Stay invoice: kind, reservation_id, service, price_option, date, name, description. Null for other items.', nullable: true, example: ['status' => 'confirmed', 'service' => ['id' => 31, 'type' => 'recreation_riding', 'name' => ['en' => 'Beginner ride', 'ar' => 'ركوب للمبتدئين']], 'trainer' => ['id' => 6, 'name' => 'Fahad'], 'price_option' => ['id' => 70, 'name' => '60 min', 'quantity' => 60, 'unit' => 'minute'], 'date' => '2026-06-05', 'end_date' => null, 'start_time' => '16:00', 'end_time' => '17:00', 'quantity' => 2])]
    #[ResponseField('coupon_code', 'string', 'Coupon code applied at checkout; null when none.', nullable: true, example: 'SUMMER25')]
    #[ResponseField('adjustments', 'object[]', 'Discounts applied. Only with `purchases.view_prices`.', required: false)]
    #[ResponseField('adjustments[].type', 'string', '`promotion` (automatic) or `coupon`.', enum: ['promotion', 'coupon'], example: 'coupon')]
    #[ResponseField('adjustments[].purchase_item_id', 'integer', 'Discounted item; null for order-level discounts.', nullable: true, example: 9001)]
    #[ResponseField('adjustments[].promotion_id', 'integer', 'Promotion ID.', example: 12)]
    #[ResponseField('adjustments[].code', 'string', 'Coupon code; null for automatic promotions.', nullable: true, example: 'SUMMER25')]
    #[ResponseField('adjustments[].label', 'string', 'Label shown to the client.', example: '15% off rides')]
    #[ResponseField('adjustments[].amount', 'integer', 'Discount in halalas.', example: 4500)]
    #[ResponseField('adjustments[].funded_by', 'string', 'Who bears it: `platform`, `center` or `shared`.', example: 'center')]
    #[ResponseField('adjustments[].center_share_amount', 'integer', 'Part borne by the center in halalas.', example: 4500)]
    #[ResponseField('payments', 'object[]', 'Payments, oldest first. Only with `purchases.view_prices`.', required: false)]
    #[ResponseField('payments[].id', 'integer', 'Payment ID.', example: 801)]
    #[ResponseField('payments[].method', 'string', 'Payment method.', enum: PaymentMethod::class, example: 'online')]
    #[ResponseField('payments[].status', 'string', 'Payment status.', enum: PaymentStatus::class, example: 'succeeded')]
    #[ResponseField('payments[].payment_company', 'string', 'Gateway (`moyasar`, `tamara`); null for offline payments.', nullable: true, example: 'moyasar')]
    #[ResponseField('payments[].amount', 'integer', 'Amount in halalas.', example: 29325)]
    #[ResponseField('payments[].refunded_amount', 'integer', 'Refunded so far in halalas.', example: 0)]
    #[ResponseField('payments[].paid_at', 'string', 'When it succeeded (ISO-8601).', nullable: true, example: '2026-06-01T10:00:00+00:00')]
    #[ResponseField('payments[].refunded_at', 'string', 'When it was refunded (ISO-8601).', nullable: true, example: 'No-example')]
    public function toArray(Request $request): array
    {
        $canViewPrices = $this->canViewPrices($request);

        return [
            ...parent::toArray($request),
            'items' => $this->whenLoaded('items', fn (): array => $this->resource->items->map(fn (PurchaseItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'purchasable_type' => class_basename($item->purchasable_type),
                'purchasable_id' => $item->purchasable_id,
                'quantity' => $item->quantity,
                ...($canViewPrices ? [
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                    'discount_amount' => $item->discount_amount,
                    'tax_amount' => $item->tax_amount,
                    'total_amount' => $item->total_amount,
                ] : []),
                'details' => $this->details($item),
            ])->all()),
            'coupon_code' => $this->resource->coupon_code,
            'adjustments' => $this->when($canViewPrices, fn () => $this->whenLoaded('adjustments', fn (): array => $this->resource->adjustments->map(fn (PurchaseAdjustment $adjustment): array => [
                'type' => $adjustment->type,
                'purchase_item_id' => $adjustment->purchase_item_id,
                'promotion_id' => $adjustment->promotion_id,
                'code' => $adjustment->code,
                'label' => $adjustment->label,
                'amount' => $adjustment->amount,
                'funded_by' => $adjustment->funded_by,
                'center_share_amount' => $adjustment->center_share_amount,
            ])->all())),
            'payments' => $this->when($canViewPrices, fn () => $this->whenLoaded('payments', fn (): array => $this->resource->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'method' => $payment->method->value,
                'status' => $payment->status->value,
                'payment_company' => $payment->payment_company,
                'amount' => $payment->amount,
                'refunded_amount' => $payment->refunded_amount,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'refunded_at' => $payment->refunded_at?->toIso8601String(),
            ])->all())),
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
            $purchasable instanceof StayInvoice => [
                'kind' => $purchasable->kind->value,
                'reservation_id' => $purchasable->reservation_id,
                'service' => $this->service($purchasable->service),
                'price_option' => $this->priceOption($purchasable->priceOption),
                'date' => $purchasable->date->toDateString(),
                'name' => $purchasable->name,
                'description' => $purchasable->description,
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
