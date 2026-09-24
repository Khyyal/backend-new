<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Http\Resources\CityResource;

class CenterResource extends JsonResource
{


    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "slug" => $this->slug,
            "contact_phone" => $this->contact_phone,
            "status" => $this->status,
            "points" => $this->points,
            "address" => $this->address,
            "lat" => $this->lat,
            "lng" => $this->lng,
            "description" => $this->description,
            "city" => new CityResource($this->whenLoaded('city')),
        ];
    }
}
