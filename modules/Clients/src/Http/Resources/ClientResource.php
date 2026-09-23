<?php

namespace Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Http\Resources\CityResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone_number' => $this->phone_number,
            'city_id' => $this->city_id,
            'city' => new CityResource($this->whenLoaded('city')),
            'needs_onboarding' => $this->first_name === null || $this->last_name === null,
        ];
    }
}
