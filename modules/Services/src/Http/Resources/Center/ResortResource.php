<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Models\Resort;
use Modules\Support\Http\Resources\MediaResource;

/**
 * @property Resort $resource
 */
class ResortResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $service = $this->resource->service;

        return [
            'id' => $this->resource->id,
            'service_id' => $service->id,
            'center_id' => $service->center_id,
            'slug' => $service->slug,
            'status' => $service->status,
            'name' => $service->getTranslations('name'),
            'description' => $service->getTranslations('description'),
            'days' => $service->priceOptions->whereNotNull('quantity')->sortBy('quantity')->map(fn ($option) => [
                'day' => $option->quantity,
                'price' => $option->price,
            ])->values(),
            'price_options' => $service->priceOptions->whereNull('quantity')->map(fn ($option) => [
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
