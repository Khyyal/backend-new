<?php

namespace Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Support\Http\Resources\CityResource;

class ClientResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Client ID.', example: 77)]
    #[ResponseField('first_name', 'string', 'First name; null until onboarding.', nullable: true, example: 'Noura')]
    #[ResponseField('last_name', 'string', 'Last name; null until onboarding.', nullable: true, example: 'Al-Qahtani')]
    #[ResponseField('phone_number', 'string', 'Phone used for OTP login.', example: '+966500000003')]
    #[ResponseField('city_id', 'integer', 'City ID; null when none.', nullable: true, example: 1)]
    #[ResponseField('city', 'object', 'The city (`CityResource`); present when loaded, null when the client has none.', required: false, nullable: true)]
    #[ResponseField('city.id', 'integer', 'City ID.', example: 1)]
    #[ResponseField('city.name', 'string', 'City name in the request locale.', example: 'Riyadh')]
    #[ResponseField('city.radius', 'integer', 'Coverage radius in km.', example: 40)]
    #[ResponseField('needs_onboarding', 'boolean', 'True while `first_name` or `last_name` is missing; send them to `PUT /api/v1/clients/profile`.', example: false)]
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
