<?php

namespace Modules\Support\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;

class CityResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'City ID.', example: 1)]
    #[ResponseField('name', 'string', 'City name in the request locale.', example: 'Riyadh')]
    #[ResponseField('radius', 'integer', 'Coverage radius in km around the city point.', example: 40)]
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,
            /** @var string */
            'name' => $this->name,
            'radius' => $this->radius,
        ];
    }
}
