<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Support\Models\Block;

/**
 * @property Block $resource
 */
class BlockedClientResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Block ID.', example: 3)]
    #[ResponseField('client_id', 'integer', 'Blocked client ID.', example: 77)]
    #[ResponseField('client', 'string', 'Client full name.', nullable: true, example: 'Noura Al-Qahtani')]
    #[ResponseField('phone_number', 'string', 'Client phone.', nullable: true, example: '+966500000003')]
    #[ResponseField('reason', 'string', 'Why the center blocked the client.', example: 'Repeated no-shows.')]
    #[ResponseField('blocked_at', 'string', 'When the block was created (ISO-8601).', example: '2026-05-20T09:30:00.000000Z')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'client_id' => $this->resource->blockable_id,
            'client' => $this->resource->blockable?->full_name,
            'phone_number' => $this->resource->blockable?->phone_number,
            'reason' => $this->resource->reason,
            'blocked_at' => $this->resource->created_at,
        ];
    }
}
