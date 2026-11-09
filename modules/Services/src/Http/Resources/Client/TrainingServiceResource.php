<?php

namespace Modules\Services\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Services\Models\Trainer;
use Modules\Services\Models\TrainingService;
use Modules\Support\Http\Resources\MediaResource;

/**
 * Client-facing shape of a service's details.
 *
 * @property TrainingService $resource
 */
class TrainingServiceResource extends JsonResource
{
    /**
     * Upcoming availability keyed by trainer id.
     *
     * @var array<int, array<int, array<string, mixed>>>|null
     */
    private ?array $availability = null;

    /**
     * @param  array<int, array<int, array<string, mixed>>>|null  $availability  keyed by trainer id
     */
    public function withAvailability(?array $availability): static
    {
        $this->availability = $availability;

        return $this;
    }

    #[ResponseField('id', 'integer', 'ID of the type-specific record (use it in the URL of this resource).', example: 12)]
    #[ResponseField('service_id', 'integer', 'ID of the underlying service (used by bookings, blocks and promotions).', example: 31)]
    #[ResponseField('slug', 'string', 'Slug, unique per center.', example: 'beginner-ride')]
    #[ResponseField('name', 'object', 'Service name. Keyed by locale.')]
    #[ResponseField('name.ar', 'string', 'Arabic text.', example: 'ركوب للمبتدئين')]
    #[ResponseField('name.en', 'string', 'English text (may be absent).', required: false, example: 'Beginner ride')]
    #[ResponseField('description', 'object', 'Service description. Keyed by locale.')]
    #[ResponseField('description.ar', 'string', 'Arabic text.', example: 'جولة بإشراف مدرب للمبتدئين.')]
    #[ResponseField('description.en', 'string', 'English text (may be absent).', required: false, example: 'A guided ride for first-timers.')]
    #[ResponseField('settings', 'object', 'Booleans keyed by setting: `enable_cash_on_arrival`, `enable_single_session_booking`, `enable_package_booking`.', example: ['enable_cash_on_arrival' => true, 'enable_single_session_booking' => false, 'enable_package_booking' => false])]
    #[ResponseField('price_options', 'object[]', 'Price options by duration (1-20).')]
    #[ResponseField('price_options[].id', 'integer', 'Price option ID (used when booking).', example: 70)]
    #[ResponseField('price_options[].duration', 'integer', 'Session length in minutes.', example: 60)]
    #[ResponseField('price_options[].price', 'integer', 'Price in halalas.', example: 15000)]
    #[ResponseField('price_options[].package', 'object', 'Session package sold for this option; null when none.', nullable: true)]
    #[ResponseField('price_options[].package.id', 'integer', 'Service package ID (used by memberships and package purchases).', example: 5)]
    #[ResponseField('price_options[].package.count', 'integer', 'Sessions in the package.', example: 8)]
    #[ResponseField('price_options[].package.price', 'integer', 'Package price in halalas.', example: 100000)]
    #[ResponseField('price_options[].package.duration', 'integer', 'Validity in months.', example: 3)]
    #[ResponseField('trainers', 'object[]', 'Trainers teaching it, each with their availability (computed from the trainer\'s schedules and hourly limit, shared across their services).')]
    #[ResponseField('trainers[].id', 'integer', 'Trainer ID.', example: 6)]
    #[ResponseField('trainers[].name', 'string', 'Trainer name.', example: 'Fahad')]
    #[ResponseField('trainers[].availability', 'object[]', 'Today and the next 14 days. A day is available when any of its slots is.')]
    #[ResponseField('trainers[].availability[].date', 'string', 'Day, `Y-m-d`.', example: '2026-06-05')]
    #[ResponseField('trainers[].availability[].available', 'boolean', 'Any slot bookable.', example: true)]
    #[ResponseField('trainers[].availability[].hours', 'object[]', 'One-hour slots of the day\'s schedule windows. A slot is available when it has not started, is not blocked, has tickets left and bookings are open.')]
    #[ResponseField('trainers[].availability[].hours[].hour', 'string', 'Slot start, `HH:MM`.', example: '16:00')]
    #[ResponseField('trainers[].availability[].hours[].available', 'boolean', 'Slot bookable.', example: true)]
    #[ResponseField('trainers[].availability[].hours[].available_tickets', 'integer', 'Tickets left; null without an hourly limit.', nullable: true, example: 3)]
    #[ResponseField('trainers[].availability[].hours[].max_tickets', 'integer', 'Hourly limit; null without one.', nullable: true, example: 5)]
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

        return [
            'id' => $this->resource->id,
            'service_id' => $service->id,
            'slug' => $service->slug,
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
                ->map(fn (Trainer $trainer) => TrainerResource::make($trainer)
                    ->withAvailability($this->availability[$trainer->id] ?? null))
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
