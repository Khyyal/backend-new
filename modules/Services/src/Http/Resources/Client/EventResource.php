<?php

namespace Modules\Services\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Models\Event;
use Modules\Support\Http\Resources\MediaResource;

/**
 * Client-facing shape of a service's details.
 *
 * @property Event $resource
 */
class EventResource extends JsonResource
{
    /**
     * @var array<int, array{day: string, available: bool, available_tickets: int|null, max_tickets: int|null}>|null
     */
    private ?array $availability = null;

    /**
     * @param  array<int, array{day: string, available: bool, available_tickets: int|null, max_tickets: int|null}>|null  $availability
     */
    public function withAvailability(?array $availability): static
    {
        $this->availability = $availability;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $service = $this->resource->service;

        return [
            'id' => $this->resource->id,
            'service_id' => $service->id,
            'slug' => $service->slug,
            'name' => $service->getTranslations('name'),
            'description' => $service->getTranslations('description'),
            'occurrence_type' => $this->resource->occurrence_type,
            'start_date' => $this->resource->start_date->format('Y-m-d'),
            'end_date' => $this->resource->end_date->format('Y-m-d'),
            'open_date' => $this->resource->open_date->format('Y-m-d'),
            'close_date' => $this->resource->close_date->format('Y-m-d'),
            'max_tickets_per_day' => $this->resource->max_tickets_per_day,
            'settings' => $this->resource->settingValues(),
            'price_options' => $service->priceOptions->map(fn ($option) => [
                'id' => $option->id,
                'name' => $option->name,
                'price' => $option->price,
            ])->values(),
            'availability' => $this->availability,
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
