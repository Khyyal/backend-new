<?php

namespace Modules\Services\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Services\Models\Membership;
use Modules\Services\Models\MembershipPackage;
use Modules\Support\Http\Resources\MediaResource;

/**
 * Client-facing shape of a service's details.
 *
 * @property Membership $resource
 */
class MembershipResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'ID of the type-specific record (use it in the URL of this resource).', example: 12)]
    #[ResponseField('service_id', 'integer', 'ID of the underlying service (used by bookings, blocks and promotions).', example: 31)]
    #[ResponseField('slug', 'string', 'Slug, unique per center.', example: 'beginner-ride')]
    #[ResponseField('name', 'object', 'Service name. Keyed by locale.')]
    #[ResponseField('name.ar', 'string', 'Arabic text.', example: 'ركوب للمبتدئين')]
    #[ResponseField('name.en', 'string', 'English text (may be absent).', required: false, example: 'Beginner ride')]
    #[ResponseField('description', 'object', 'Service description. Keyed by locale.')]
    #[ResponseField('description.ar', 'string', 'Arabic text.', example: 'جولة بإشراف مدرب للمبتدئين.')]
    #[ResponseField('description.en', 'string', 'English text (may be absent).', required: false, example: 'A guided ride for first-timers.')]
    #[ResponseField('price', 'integer', 'Membership price in halalas.', example: 250000)]
    #[ResponseField('duration', 'integer', 'Validity in months (1-120).', example: 6)]
    #[ResponseField('color', 'string', 'Card color, `#RRGGBB`.', example: '#8B4513')]
    #[ResponseField('visit_service', 'object', 'Visit service with free entries for members; null when none.', nullable: true)]
    #[ResponseField('visit_service.id', 'integer', 'Service ID.', example: 33)]
    #[ResponseField('visit_service.name', 'object', 'Name keyed by locale.', example: ['ar' => 'زيارة', 'en' => 'Visit'])]
    #[ResponseField('max_visits_per_day', 'integer', 'Free visit entries per day; null without a visit service.', nullable: true, example: 1)]
    #[ResponseField('packages', 'object[]', 'Included service packages.')]
    #[ResponseField('packages[].id', 'integer', 'Membership package ID.', example: 2)]
    #[ResponseField('packages[].service_package_id', 'integer', 'Service package ID.', example: 5)]
    #[ResponseField('packages[].price_option_id', 'integer', 'Price option of that package.', example: 70)]
    #[ResponseField('packages[].service', 'object', 'The package\'s service (`id`, `name` by locale, `type`); null when it was deleted.', nullable: true, example: ['id' => 31, 'name' => ['ar' => 'تدريب', 'en' => 'Training'], 'type' => 'training'])]
    #[ResponseField('packages[].count', 'integer', 'Sessions included.', example: 8)]
    #[ResponseField('packages[].duration', 'integer', 'Package validity in months.', example: 3)]
    #[ResponseField('packages[].price', 'integer', 'Stand-alone package price in halalas.', example: 100000)]
    #[ResponseField('packages[].trainer', 'object', 'Trainer fixed for a training package; null otherwise.', nullable: true, example: ['id' => 6, 'name' => 'Fahad'])]
    #[ResponseField('packages[].available', 'boolean', 'False when the package or its service was deleted.', example: true)]
    #[ResponseField('cover', 'object', 'Cover image (`MediaResource`); null when none.', required: false, nullable: true)]
    #[ResponseField('cover.id', 'string', 'Media UUID.', example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
    #[ResponseField('cover.status', 'string', 'Media lifecycle state.', enum: ['temporary', 'attached'], example: 'attached')]
    #[ResponseField('cover.url', 'string', 'Public URL of the original file.', example: 'https://cdn.khyyal.com/media/12/photo.jpg')]
    #[ResponseField('cover.filename', 'string', 'Stored file name.', example: 'photo.jpg')]
    #[ResponseField('cover.mime_type', 'string', 'MIME type.', example: 'image/jpeg')]
    #[ResponseField('cover.size', 'integer', 'File size in bytes.', example: 184233)]
    #[ResponseField('images', 'object[]', 'Gallery images in display order (max 10).', required: false, nullable: false)]
    #[ResponseField('images[].id', 'string', 'Media UUID.', example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
    #[ResponseField('images[].status', 'string', 'Media lifecycle state.', enum: ['temporary', 'attached'], example: 'attached')]
    #[ResponseField('images[].url', 'string', 'Public URL of the original file.', example: 'https://cdn.khyyal.com/media/12/photo.jpg')]
    #[ResponseField('images[].filename', 'string', 'Stored file name.', example: 'photo.jpg')]
    #[ResponseField('images[].mime_type', 'string', 'MIME type.', example: 'image/jpeg')]
    #[ResponseField('images[].size', 'integer', 'File size in bytes.', example: 184233)]
    public function toArray(Request $request): array
    {
        $service = $this->resource->service;
        $visitService = $this->resource->visitService;

        return [
            'id' => $this->resource->id,
            'service_id' => $service->id,
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
