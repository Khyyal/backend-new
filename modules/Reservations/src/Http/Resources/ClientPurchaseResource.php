<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Models\PurchaseItem;

/**
 * A purchase as its client sees it. Amounts are halalas; `payment_method` and `payment_status` come from the
 * latest payment (`null` without one).
 *
 * @property Purchase $resource
 */
class ClientPurchaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'integer', 'Purchase ID.', example: 5521)]
    #[ResponseField('code', 'string', 'Public 6-character purchase code, unique; use it to look the purchase up.', example: 'K7QX2M')]
    #[ResponseField('status', 'string', 'Purchase status.', enum: PurchaseStatus::class, example: 'confirmed')]
    #[ResponseField('center', 'object', 'The center bought from (`id`, `name`); null when deleted.', nullable: true, example: ['id' => 8, 'name' => 'Al Fursan Stables'])]
    #[ResponseField('items', 'object[]', 'Line items with `id`, `name`, `purchasable_type` (`Reservation`, `ClientPackage`, `ClientMembership`), `purchasable_id`, `quantity`, `unit_price`, `subtotal`, `discount_amount`, `tax_amount`, `total_amount` (halalas).', example: [['id' => 9001, 'name' => 'Beginner ride - 60 min', 'purchasable_type' => 'Reservation', 'purchasable_id' => 311, 'quantity' => 2, 'unit_price' => 15000, 'subtotal' => 30000, 'discount_amount' => 4500, 'tax_amount' => 3825, 'total_amount' => 29325]])]
    #[ResponseField('subtotal', 'integer', 'Before discounts, in halalas.', example: 30000)]
    #[ResponseField('discount_amount', 'integer', 'Discount in halalas.', example: 4500)]
    #[ResponseField('tax_amount', 'integer', 'VAT in halalas.', example: 3825)]
    #[ResponseField('total_amount', 'integer', 'Charged, in halalas.', example: 29325)]
    #[ResponseField('coupon_code', 'string', 'Applied coupon.', nullable: true, example: 'SUMMER25')]
    #[ResponseField('payment_method', 'string', 'Method of the latest payment; null without payments.', nullable: true, enum: PaymentMethod::class, example: 'online')]
    #[ResponseField('payment_status', 'string', 'Status of the latest payment; null without payments.', nullable: true, enum: PaymentStatus::class, example: 'succeeded')]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    public function toArray(Request $request): array
    {
        $purchase = $this->resource;

        return [
            'id' => $purchase->id,
            'code' => $purchase->code,
            'status' => $purchase->status->value,
            'center' => $purchase->merchant === null ? null : [
                'id' => $purchase->merchant->id,
                'name' => $purchase->merchant->name,
            ],
            'items' => $this->whenLoaded('items', fn (): array => $purchase->items->map(fn (PurchaseItem $item): array => [
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
            ])->all()),
            'subtotal' => $purchase->subtotal,
            'discount_amount' => $purchase->discount_amount,
            'tax_amount' => $purchase->tax_amount,
            'total_amount' => $purchase->total_amount,
            'coupon_code' => $purchase->coupon_code,
            'payment_method' => $purchase->latestPayment?->method->value,
            'payment_status' => $purchase->latestPayment?->status->value,
            'created_at' => $purchase->created_at?->toIso8601String(),
        ];
    }
}
