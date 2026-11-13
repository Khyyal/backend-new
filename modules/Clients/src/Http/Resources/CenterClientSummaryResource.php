<?php

namespace Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Clients\Models\Client;

/**
 * A client as returned by the center phone lookup and client creation.
 *
 * @property Client $resource
 */
class CenterClientSummaryResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Client ID (use it as `client_id` when booking or adding a horse).', example: 77)]
    #[ResponseField('first_name', 'string', 'First name; null until the client completes onboarding.', nullable: true, example: 'Noura')]
    #[ResponseField('last_name', 'string', 'Last name; null until the client completes onboarding.', nullable: true, example: 'Al-Qahtani')]
    #[ResponseField('phone_number', 'string', 'Phone number.', example: '+966500000003')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'phone_number' => $this->resource->phone_number,
        ];
    }
}
