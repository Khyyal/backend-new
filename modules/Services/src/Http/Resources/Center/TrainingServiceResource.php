<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Models\Trainer;
use Modules\Services\Models\TrainingService;
use Modules\Support\Http\Resources\MediaResource;

/**
 * @property TrainingService $resource
 */
class TrainingServiceResource extends JsonResource
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
            'settings' => $this->resource->settingValues(),
            'price_options' => $service->priceOptions->map(fn ($option) => [
                'id' => $option->id,
                'duration' => $option->quantity,
                'price' => $option->price,
                'package' => $option->package === null ? null : [
                    'id' => $option->package->id,
                    'count' => $option->package->count,
                    'price' => $option->package->price,
                    'duration' => $option->package->duration,
                ],
            ])->values(),
            'trainers' => $this->resource->trainers
                ->sortBy('id')
                ->map(fn (Trainer $trainer) => [
                    'id' => $trainer->id,
                    'name' => $trainer->name,
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
