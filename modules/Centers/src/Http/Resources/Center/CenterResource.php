<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Http\Resources\CityResource;
use Modules\Support\Http\Resources\MediaResource;

class CenterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'contact_phone' => $this->contact_phone,
            'status' => $this->status,
            'points' => $this->points,
            'address' => $this->address,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'description' => $this->description,
            'city' => new CityResource($this->whenLoaded('city')),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])),
            'logo' => $this->whenLoaded('media', fn () => $this->singleMedia('logo')),
            'cover' => $this->whenLoaded('media', fn () => $this->singleMedia('cover')),
            'images' => $this->whenLoaded('media', fn () => MediaResource::collection(
                $this->media->where('collection_name', 'images')->sortBy('order_column')->values()
            )),
        ];
    }

    private function singleMedia(string $collection): ?MediaResource
    {
        $media = $this->media->firstWhere('collection_name', $collection);

        return $media === null ? null : new MediaResource($media);
    }
}
