<?php

namespace Modules\Centers\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Centers\Models\Center;
use Modules\Support\Http\Resources\CityResource;

/**
 * Full center details. `distance_km` is null unless `lat`/`lng` were sent.
 * `services` is an overview of the center's active services.
 *
 * @property Center $resource
 */
class CenterDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $center = $this->resource;
        $distance = $center->getAttribute('distance_km');

        return [
            'id' => $center->id,
            'slug' => $center->slug,
            'name' => $center->name,
            'description' => $center->description,
            'address' => $center->address,
            'city' => new CityResource($this->whenLoaded('city')),
            'location' => [
                'lat' => $center->lat,
                'lng' => $center->lng,
            ],
            'contact_phone' => $center->contact_phone,
            'distance_km' => $distance !== null ? round((float) $distance, 2) : null,
            'tags' => $this->whenLoaded('tags', fn () => $center->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])),
            'logo' => $this->whenLoaded('media', fn () => $this->mediaUrl('logo', 'thumb')),
            'cover' => $this->whenLoaded('media', fn () => $this->mediaUrl('cover', 'cover-thumb')),
            'images' => $this->whenLoaded('media', fn () => $center->media
                ->where('collection_name', 'images')
                ->map(fn ($media) => $media->getFullUrl('gallery-thumb'))
                ->values()),
            'services' => $this->whenLoaded('services', fn () => $center->services->map(fn ($service) => [
                'id' => $service->id,
                'slug' => $service->slug,
                'name' => $service->name,
                'cover' => $service->getFirstMedia('cover')?->getFullUrl('cover-thumb'),
                'start_price' => $service->start_price !== null ? (int) $service->start_price : null,
            ])->values()),
        ];
    }

    private function mediaUrl(string $collection, string $conversion): ?string
    {
        return $this->resource->media->firstWhere('collection_name', $collection)?->getFullUrl($conversion);
    }
}
