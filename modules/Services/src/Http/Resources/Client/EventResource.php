<?php

namespace Modules\Services\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Services\Enums\EventOccurrenceType;
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

    #[ResponseField('id', 'integer', 'ID of the type-specific record (use it in the URL of this resource).', example: 12)]
    #[ResponseField('service_id', 'integer', 'ID of the underlying service (used by bookings, blocks and promotions).', example: 31)]
    #[ResponseField('slug', 'string', 'Slug, unique per center.', example: 'beginner-ride')]
    #[ResponseField('name', 'object', 'Service name. Keyed by locale.')]
    #[ResponseField('name.ar', 'string', 'Arabic text.', example: 'ركوب للمبتدئين')]
    #[ResponseField('name.en', 'string', 'English text (may be absent).', required: false, example: 'Beginner ride')]
    #[ResponseField('description', 'object', 'Service description. Keyed by locale.')]
    #[ResponseField('description.ar', 'string', 'Arabic text.', example: 'جولة بإشراف مدرب للمبتدئين.')]
    #[ResponseField('description.en', 'string', 'English text (may be absent).', required: false, example: 'A guided ride for first-timers.')]
    #[ResponseField('occurrence_type', 'string', '`specific_day`: tickets are for a chosen day between `start_date` and `end_date`; `general`: one ticket valid for the whole event.', enum: EventOccurrenceType::class, example: 'specific_day')]
    #[ResponseField('start_date', 'string', 'First event day, `Y-m-d`.', example: '2026-07-01')]
    #[ResponseField('end_date', 'string', 'Last event day, `Y-m-d`.', example: '2026-07-03')]
    #[ResponseField('open_date', 'string', 'Ticket sales open, `Y-m-d`.', example: '2026-06-01')]
    #[ResponseField('close_date', 'string', 'Ticket sales close, `Y-m-d`.', example: '2026-06-30')]
    #[ResponseField('max_tickets_per_day', 'integer', 'Tickets sold per event day.', example: 200)]
    #[ResponseField('settings', 'object', 'Booleans keyed by setting: `enable_cash_on_arrival`.', example: ['enable_cash_on_arrival' => true])]
    #[ResponseField('price_options', 'object[]', 'Price options (1-20).')]
    #[ResponseField('price_options[].id', 'integer', 'Price option ID (used when booking).', example: 70)]
    #[ResponseField('price_options[].name', 'string', 'Option name.', example: 'Adult ticket')]
    #[ResponseField('price_options[].price', 'integer', 'Price in halalas.', example: 5000)]
    #[ResponseField('availability', 'object[]', 'Empty for `general` events. For `specific_day` events, every event day; a day is available while sales are open today, it has not passed, is not blocked all day and has tickets left.')]
    #[ResponseField('availability[].day', 'string', 'Event day, `Y-m-d`.', example: '2026-07-01')]
    #[ResponseField('availability[].available', 'boolean', 'Bookable.', example: true)]
    #[ResponseField('availability[].available_tickets', 'integer', 'Tickets left.', example: 150)]
    #[ResponseField('availability[].max_tickets', 'integer', 'Tickets per day.', example: 200)]
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
