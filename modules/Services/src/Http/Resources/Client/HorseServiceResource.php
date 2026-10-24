<?php

namespace Modules\Services\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Models\HorseService;
use Modules\Support\Http\Resources\MediaResource;

/**
 * Client-facing shape of a service's details.
 *
 * @property HorseService $resource
 */
class HorseServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $service = $this->resource->service;

        return [
            'id' => $this->resource->id,
            'service_id' => $service->id,
            'slug' => $service->slug,
            'name' => $service->getTranslations('name'),
            'description' => $service->getTranslations('description'),
            'price_options' => $service->priceOptions->map(fn ($option) => [
                'id' => $option->id,
                'name' => $option->name,
                'price' => $option->price,
            ])->values(),
            'cover' => $this->singleMedia('cover'),
            'images' => MediaResource::collection(
                $service->media->where('collection_name', 'images')->sortBy('order_column')->values()
            ),
        ];
    }

    private function singleMedia(string $collection): ?MediaResource
    {
        $media = $this->resource->service->media->firstWhere('collection_name', $collection);

        return $media === null ? null : new MediaResource($media);
    }
}
