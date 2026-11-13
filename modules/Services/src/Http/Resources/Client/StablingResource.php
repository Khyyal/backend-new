<?php

namespace Modules\Services\Http\Resources\Client;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Stabling;
use Modules\Support\Http\Resources\MediaResource;

/**
 * Client-facing shape of a service's details.
 *
 * @property Stabling $resource
 */
class StablingResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'ID of the type-specific record (use it in the URL of this resource).', example: 12)]
    #[ResponseField('service_id', 'integer', 'ID of the underlying service (used by bookings, blocks and promotions).', example: 31)]
    #[ResponseField('slug', 'string', 'Slug, unique per center.', example: 'monthly-stabling')]
    #[ResponseField('name', 'object', 'Service name. Keyed by locale.')]
    #[ResponseField('name.ar', 'string', 'Arabic text.', example: 'إسطبل شهري')]
    #[ResponseField('name.en', 'string', 'English text (may be absent).', required: false, example: 'Monthly stabling')]
    #[ResponseField('description', 'object', 'Service description. Keyed by locale.')]
    #[ResponseField('description.ar', 'string', 'Arabic text.', example: 'إيواء الخيل مع العلف والتنظيف.')]
    #[ResponseField('description.en', 'string', 'English text (may be absent).', required: false, example: 'Horse boarding with feed and cleaning.')]
    #[ResponseField('monthly_prices', 'object[]', 'Monthly prices (1-12), sorted by months.')]
    #[ResponseField('monthly_prices[].id', 'integer', 'Price option ID.', example: 70)]
    #[ResponseField('monthly_prices[].months', 'integer', 'Number of months.', example: 1)]
    #[ResponseField('monthly_prices[].price', 'integer', 'Price in halalas.', example: 150000)]
    #[ResponseField('daily_price', 'object', 'Price per day; null when the service has none.', nullable: true)]
    #[ResponseField('daily_price.id', 'integer', 'Price option ID.', example: 71)]
    #[ResponseField('daily_price.price', 'integer', 'Price per day in halalas.', example: 6000)]
    #[ResponseField('extra_options', 'object[]', 'Named extras (max 20); not counted in the start price.')]
    #[ResponseField('extra_options[].id', 'integer', 'Price option ID.', example: 72)]
    #[ResponseField('extra_options[].name', 'string', 'Extra name.', example: 'Daily grooming')]
    #[ResponseField('extra_options[].price', 'integer', 'Price in halalas.', example: 30000)]
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
            ...$this->prices($service->priceOptions),
            'cover' => $this->singleMedia('cover'),
            'images' => MediaResource::collection(
                $service->media->where('collection_name', 'images')->sortBy('order_column')->values()
            ),
        ];
    }

    /**
     * @param  Collection<int, PriceOption>  $options
     * @return array{monthly_prices: \Illuminate\Support\Collection<int, array{id: int, months: int, price: int}>, daily_price: array{id: int, price: int}|null, extra_options: \Illuminate\Support\Collection<int, array{id: int, name: string, price: int}>}
     */
    private function prices(Collection $options): array
    {
        $daily = $options->firstWhere('unit', PriceOptionUnit::DAY);

        return [
            'monthly_prices' => $options->where('unit', PriceOptionUnit::MONTH)->sortBy('quantity')->map(fn (PriceOption $option) => [
                'id' => $option->id,
                'months' => $option->quantity,
                'price' => $option->price,
            ])->values(),
            'daily_price' => $daily === null ? null : ['id' => $daily->id, 'price' => $daily->price],
            'extra_options' => $options->where('unit', PriceOptionUnit::OPTION)->map(fn (PriceOption $option) => [
                'id' => $option->id,
                'name' => $option->name,
                'price' => $option->price,
            ])->values(),
        ];
    }

    private function singleMedia(string $collection): ?MediaResource
    {
        $media = $this->resource->service->media->firstWhere('collection_name', $collection);

        return $media === null ? null : new MediaResource($media);
    }
}
