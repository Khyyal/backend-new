<?php

namespace Modules\Support\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Models\City;


class CityResource extends JsonResource
{

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
