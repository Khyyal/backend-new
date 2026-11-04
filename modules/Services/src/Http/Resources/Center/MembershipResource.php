<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Models\Membership;
use Modules\Services\Models\MembershipPackage;
use Modules\Support\Http\Resources\MediaResource;

/**
 * @property Membership $resource
 */
class MembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $service = $this->resource->service;
        $visitService = $this->resource->visitService;

        return [
            'id' => $this->resource->id,
            'service_id' => $service->id,
            'center_id' => $service->center_id,
            'status' => $service->status,
            'slug' => $service->slug,
            'name' => $service->getTranslations('name'),
            'description' => $service->getTranslations('description'),
            'price' => $service->priceOptions->first()?->price,
            'duration' => $this->resource->duration,
            'color' => $this->resource->color,
            'visit_service' => $visitService === null ? null : [
                'id' => $visitService->id,
                'name' => $visitService->getTranslations('name'),
            ],
            'max_visits_per_day' => $this->resource->max_visits_per_day,
            'packages' => $this->resource->packages
                ->sortBy('id')
                ->map(fn (MembershipPackage $package) => $this->package($package))
                ->values(),
            'cover' => $this->singleMedia('cover'),
            'images' => MediaResource::collection(
                $service->media->where('collection_name', 'images')->sortBy('order_column')->values()
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function package(MembershipPackage $package): array
    {
        $servicePackage = $package->servicePackage;
        $packageService = $servicePackage->service;

        return [
            'id' => $package->id,
            'service_package_id' => $package->service_package_id,
            'price_option_id' => $package->price_option_id,
            'service' => $packageService === null ? null : [
                'id' => $packageService->id,
                'name' => $packageService->getTranslations('name'),
                'type' => $packageService->type,
            ],
            'count' => $servicePackage->count,
            'duration' => $servicePackage->duration,
            'price' => $servicePackage->price,
            'trainer' => $package->trainer === null ? null : [
                'id' => $package->trainer->id,
                'name' => $package->trainer->name,
            ],
            'available' => ! $servicePackage->trashed() && $packageService !== null,
        ];
    }

    private function singleMedia(string $collection): ?MediaResource
    {
        $media = $this->resource->service->media->firstWhere('collection_name', $collection);

        return $media === null ? null : new MediaResource($media);
    }
}
