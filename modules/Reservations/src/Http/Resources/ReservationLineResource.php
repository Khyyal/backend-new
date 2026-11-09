<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Reservations\Enums\ReservationLineKind;
use Modules\Reservations\Models\ReservationLine;

/**
 * @property ReservationLine $resource
 */
class ReservationLineResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Line ID.', example: 1)]
    #[ResponseField('kind', 'string', '`ticket` or `addon`.', enum: ReservationLineKind::class, example: 'ticket')]
    #[ResponseField('price_option_id', 'integer', 'Price option.', example: 71)]
    #[ResponseField('name', 'string', 'Option name.', nullable: true, example: 'Adult ticket')]
    #[ResponseField('quantity', 'integer', 'Quantity.', example: 2)]
    #[ResponseField('unit_price', 'integer', 'Unit price in halalas.', example: 5000)]
    #[ResponseField('subtotal', 'integer', 'Line subtotal in halalas.', example: 10000)]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'kind' => $this->resource->kind->value,
            'price_option_id' => $this->resource->price_option_id,
            'name' => $this->resource->priceOption?->name,
            'quantity' => $this->resource->quantity,
            'unit_price' => $this->resource->unit_price,
            'subtotal' => $this->resource->subtotal,
        ];
    }
}
