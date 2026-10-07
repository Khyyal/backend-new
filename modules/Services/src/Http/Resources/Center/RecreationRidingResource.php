<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Models\RecreationalRiding;
use Modules\Support\Http\Resources\MediaResource;

/**
 * @property RecreationalRiding $resource
 */
class RecreationRidingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $service = $this->resource->service;
        $schedules = $this->resource->schedules->sortBy([['day_of_week', 'asc'], ['start_time', 'asc']]);
        $firstDay = $schedules->first()?->day_of_week;

        return [
            'id' => $this->resource->id,
            'service_id' => $service->id,
            'center_id' => $service->center_id,
            'slug' => $service->slug,
            'status' => $service->status,
            'name' => $service->getTranslations('name'),
            'description' => $service->getTranslations('description'),
            'max_tickets_per_hour' => $this->resource->max_tickets_per_hour,
            'price_options' => $service->priceOptions->map(fn ($option) => [
                'id' => $option->id,
                'duration' => $option->quantity,
                'price' => $option->price,
            ])->values(),
            'days' => $schedules->pluck('day_of_week')->unique()->values(),
            'hours' => $schedules->where('day_of_week', $firstDay)
                ->flatMap(fn ($slot) => [substr($slot->start_time, 0, 5), substr($slot->end_time, 0, 5)])
                ->values(),
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
