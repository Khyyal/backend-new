<?php

namespace Modules\Support\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Models\City;

/**
 * A city as admins manage it: both translations, the map point and the radius in km.
 *
 * @property City $resource
 */
class CityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $city = $this->resource;

        return [
            'id' => $city->id,
            'name' => $city->getTranslations('name'),
            'lat' => $city->lat,
            'lng' => $city->lng,
            'radius' => $city->radius,
            'centers_count' => $this->whenCounted('centers'),
            'created_at' => $city->created_at?->toIso8601String(),
            'updated_at' => $city->updated_at?->toIso8601String(),
        ];
    }
}
