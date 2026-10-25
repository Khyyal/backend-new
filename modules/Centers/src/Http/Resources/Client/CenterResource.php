<?php

namespace Modules\Centers\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Centers\Models\Center;
use Modules\Support\Http\Resources\CityResource;

/**
 * @property Center $resource
 */
class CenterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'slug' => $this->resource->slug,
            'name' => $this->resource->name,
            'address' => $this->resource->address,
            'city' => new CityResource($this->whenLoaded('city')),
            'description' => $this->resource->description,
            'location' => [
                'lat' => $this->resource->lat,
                'lng' => $this->resource->lng,
            ],
            'contact_phone' => $this->resource->contact_phone,
            'tags' => $this->whenLoaded('tags', fn () => $this->resource->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])),
            'rating' => [
                'avg' => $this->resource->rating_avg !== null ? round((float) $this->resource->rating_avg, 2) : null,
                'count' => (int) ($this->resource->ratings_count ?? 0),
            ],
            'logo' => $this->whenLoaded('media', fn () => $this->thumbUrl('logo', 'thumb')),
            'cover' => $this->whenLoaded('media', fn () => $this->thumbUrl('cover', 'cover-thumb')),
        ];
    }

    private function thumbUrl(string $collection, string $conversion): ?string
    {
        return $this->resource->media->firstWhere('collection_name', $collection)?->getFullUrl($conversion);
    }
}
