<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reservations\Models\ReservationLine;

/**
 * @property ReservationLine $resource
 */
class ReservationLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'kind' => $this->resource->kind->value,
            'price_option_id' => $this->resource->price_option_id,
            'name' => $this->resource->priceOption?->name,
            'quantity' => $this->resource->quantity,
            'unit_price' => (float) $this->resource->unit_price,
            'subtotal' => (float) $this->resource->subtotal,
        ];
    }
}
