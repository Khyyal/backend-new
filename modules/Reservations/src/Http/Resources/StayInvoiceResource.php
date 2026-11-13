<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Reservations\Enums\StayInvoiceKind;
use Modules\Reservations\Models\StayInvoice;

/**
 * @property StayInvoice $resource
 */
class StayInvoiceResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Invoice ID.', example: 9)]
    #[ResponseField('reservation_id', 'integer', 'The stabling stay.', example: 412)]
    #[ResponseField('kind', 'string', 'What is billed.', enum: StayInvoiceKind::class, example: 'horse_service')]
    #[ResponseField('date', 'string', 'Day of the charge, `Y-m-d`.', example: '2026-12-10')]
    #[ResponseField('name', 'string', 'Invoice name (service and option name for service invoices).', example: 'Shoeing - Full set')]
    #[ResponseField('description', 'string', 'Details.', nullable: true, example: 'Hoof check')]
    #[ResponseField('price', 'integer', 'Computed price in halalas (option price; the total for standalone).', example: 25000)]
    #[ResponseField('total_amount', 'integer', 'Charged total in halalas.', example: 25000)]
    #[ResponseField('service', 'object', 'Billed service (`id`, `type`, `name`) for service invoices; null for standalone.', nullable: true)]
    #[ResponseField('price_option', 'object', 'Billed option (`id`, `name`, `price`); null for standalone.', nullable: true)]
    #[ResponseField('purchase', 'object', 'Purchase (`id`, `code`, `status`); null when the total is 0.', nullable: true)]
    #[ResponseField('payment_status', 'string', 'Latest payment status; null without payment.', nullable: true, example: 'pending')]
    #[ResponseField('refunded_amount', 'integer', 'Halalas refunded on the purchase\'s payments; 0 when nothing was refunded.', example: 0)]
    #[ResponseField('cancelled_at', 'string', 'When the invoice was cancelled (ISO-8601); null while active.', nullable: true, example: 'No-example')]
    #[ResponseField('cancellation_reason', 'string', 'Why it was cancelled.', nullable: true, example: 'No-example')]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-12-10T09:30:00.000000Z')]
    public function toArray(Request $request): array
    {
        $invoice = $this->resource;
        $isStandalone = $invoice->kind === StayInvoiceKind::Standalone;
        $purchase = $invoice->purchaseItem?->purchase;
        $payment = $purchase?->payments->sortByDesc('id')->first();

        return [
            'id' => $invoice->id,
            'reservation_id' => $invoice->reservation_id,
            'kind' => $invoice->kind->value,
            'date' => $invoice->date->toDateString(),
            'name' => $invoice->name,
            'description' => $invoice->description,
            'price' => $invoice->price,
            'total_amount' => $invoice->total_amount,
            'service' => $isStandalone || $invoice->service === null ? null : [
                'id' => $invoice->service->id,
                'type' => $invoice->service->type->value,
                'name' => $invoice->service->getTranslations('name'),
            ],
            'price_option' => $invoice->priceOption === null ? null : [
                'id' => $invoice->priceOption->id,
                'name' => $invoice->priceOption->name,
                'price' => $invoice->priceOption->price,
            ],
            'purchase' => $purchase === null ? null : [
                'id' => $purchase->id,
                'code' => $purchase->code,
                'status' => $purchase->status->value,
            ],
            'payment_status' => $payment?->status->value,
            'refunded_amount' => (int) ($purchase?->payments->sum('refunded_amount') ?? 0),
            'cancelled_at' => $invoice->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $invoice->cancellation_reason,
            'created_at' => $invoice->created_at,
        ];
    }
}
