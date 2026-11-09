<?php

namespace Modules\Purchase\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Purchase;

/**
 * A client purchase in the platform-wide list. `payment_method` and `payment_status` come from the latest
 * payment (`null` without one). Amounts are halalas; `platform_fee_amount` is what the center owes the platform
 * (0 once reversed).
 *
 * @property Purchase $resource
 */
class PurchaseResource extends JsonResource
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
    public function toArray(Request $request): array
    {
        $purchase = $this->resource;

        return [
            'id' => $purchase->id,
            'status' => $purchase->status->value,
            'source' => $purchase->source->value,
            'channel' => $purchase->channel?->value,
            'payment_method' => $purchase->latestPayment?->method->value,
            'payment_status' => $purchase->latestPayment?->status->value,
            'client' => $purchase->buyer === null ? null : [
                'id' => $purchase->buyer->id,
                'full_name' => $purchase->buyer->full_name,
                'phone_number' => $purchase->buyer->phone_number,
            ],
            'center' => $purchase->merchant === null ? null : [
                'id' => $purchase->merchant->id,
                'name' => $purchase->merchant->name,
            ],
            'items_count' => $purchase->items_count,
            'subtotal' => $purchase->subtotal,
            'discount_amount' => $purchase->discount_amount,
            'tax_amount' => $purchase->tax_amount,
            'total_amount' => $purchase->total_amount,
            'platform_fee_amount' => $purchase->fee?->owedAmount() ?? 0,
            'created_at' => $purchase->created_at?->toIso8601String(),
        ];
    }
}
