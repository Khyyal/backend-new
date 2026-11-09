<?php

namespace Modules\Support\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Support\Models\City;

/**
 * A city as admins manage it: both translations, the map point and the radius in km.
 *
 * @property City $resource
 */
class CityResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'City ID.', example: 1)]
    #[ResponseField('name', 'object', 'City name keyed by locale.')]
    #[ResponseField('name.en', 'string', 'English name.', example: 'Riyadh')]
    #[ResponseField('name.ar', 'string', 'Arabic name.', example: 'الرياض')]
    #[ResponseField('lat', 'number', 'Latitude of the city point.', example: 24.7136)]
    #[ResponseField('lng', 'number', 'Longitude of the city point.', example: 46.6753)]
    #[ResponseField('radius', 'integer', 'Coverage radius in km (1-1000).', example: 40)]
    #[ResponseField('centers_count', 'integer', 'Centers in the city (including deleted ones).', required: false, example: 12)]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('updated_at', 'string', 'Last update time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
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
