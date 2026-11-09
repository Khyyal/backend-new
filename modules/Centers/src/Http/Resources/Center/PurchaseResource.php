<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Purchase;

/**
 * A purchase as it appears in the center's list. `payment_method` and
 * `payment_status` come from the latest payment (`null` without one). Amounts
 * are only included when the user may view purchase prices (`purchases.view_prices`);
 * `platform_fee_amount` is the fee the center owes the platform (0 once reversed);
 * `center_amount` is what the platform owes the center for it (negative for
 * cash sales, where the center collected the money and owes the fee).
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
    public function toArray(Request $request): array
    {
        $purchase = $this->resource;

        return [
            'id' => $purchase->id,
            'status' => $purchase->status->value,
            'source' => $purchase->source->value,
            'channel' => $purchase->channel?->value,
            'payment_method' => $this->whenLoaded('latestPayment', fn (): ?string => $purchase->latestPayment?->method->value),
            'payment_status' => $this->whenLoaded('latestPayment', fn (): ?string => $purchase->latestPayment?->status->value),
            'client' => $this->whenLoaded('buyer', fn (): ?array => $purchase->buyer === null ? null : [
                'id' => $purchase->buyer->id,
                'full_name' => $purchase->buyer->full_name,
                'phone_number' => $purchase->buyer->phone_number,
            ]),
            'items_count' => $this->whenCounted('items'),
            $this->mergeWhen($this->canViewPrices($request), fn (): array => [
                'subtotal' => $purchase->subtotal,
                'discount_amount' => $purchase->discount_amount,
                'tax_amount' => $purchase->tax_amount,
                'total_amount' => $purchase->total_amount,
                'platform_fee_amount' => $this->whenLoaded('fee', fn (): int => $purchase->fee?->owedAmount() ?? 0),
                'center_amount' => $this->whenLoaded('ledgerEntries', fn (): int => (int) $purchase->ledgerEntries->sum('amount')),
            ]),
            'created_at' => $purchase->created_at?->toIso8601String(),
        ];
    }

    protected function canViewPrices(Request $request): bool
    {
        return Gate::forUser($request->user())->allows('viewPurchasePrices', $request->route('center'));
    }
}
