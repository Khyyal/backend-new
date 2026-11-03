<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\PurchaseItem;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
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
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                    'discount_amount' => (float) $item->discount_amount,
                    'tax_amount' => (float) $item->tax_amount,
                    'total_amount' => (float) $item->total_amount,
                ] : []),
                'details' => $this->details($item),
            ])->all()),
            'payments' => $this->when($canViewPrices, fn () => $this->whenLoaded('payments', fn (): array => $this->resource->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'method' => $payment->method->value,
                'status' => $payment->status->value,
                'payment_company' => $payment->payment_company,
                'amount' => (float) $payment->amount,
                'refunded_amount' => (float) $payment->refunded_amount,
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
