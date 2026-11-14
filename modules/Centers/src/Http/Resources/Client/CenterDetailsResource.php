<?php

namespace Modules\Centers\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Centers\Models\Center;
use Modules\Support\Http\Resources\CityResource;

/**
 * Full center details. `distance_km` is null unless `lat`/`lng` were sent.
 * `services` is an overview of the center's active services.
 *
 * @property Center $resource
 */
class CenterDetailsResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('slug', 'string', 'Slug; use it on `GET /api/v1/clients/centers/{slug}`.', example: 'al-fursan-stables')]
    #[ResponseField('name', 'string', 'Center name.', example: 'Al Fursan Stables')]
    #[ResponseField('address', 'string', 'Street address.', nullable: true, example: 'King Fahd Rd, Al Olaya')]
    #[ResponseField('city', 'object', 'The city (`CityResource`). Present when loaded.', required: false)]
    #[ResponseField('city.id', 'integer', 'City ID.', example: 1)]
    #[ResponseField('city.name', 'string', 'City name in the request locale.', example: 'Riyadh')]
    #[ResponseField('city.radius', 'integer', 'Coverage radius in km.', example: 40)]
    #[ResponseField('description', 'string', 'Description.', nullable: true, example: 'Riding lessons and trail rides for all levels.')]
    #[ResponseField('location', 'object', 'Map point.')]
    #[ResponseField('location.lat', 'number', 'Latitude.', nullable: true, example: 24.7136)]
    #[ResponseField('location.lng', 'number', 'Longitude.', nullable: true, example: 46.6753)]
    #[ResponseField('contact_phone', 'string', 'Public contact phone.', example: '+966500000001')]
    #[ResponseField('distance_km', 'number', 'Distance from `lat`/`lng` in km (2 decimals); null when they were not sent.', nullable: true, example: 3.42)]
    #[ResponseField('tags', 'object[]', 'Tags of the center. Present when loaded.', required: false)]
    #[ResponseField('tags[].id', 'integer', 'Tag ID.', example: 4)]
    #[ResponseField('tags[].name', 'string', 'Tag name in the request locale.', example: 'Family friendly')]
    #[ResponseField('logo', 'string', 'Logo thumbnail URL; null when none.', required: false, nullable: true, example: 'https://cdn.khyyal.com/media/40/conversions/logo-thumb.jpg')]
    #[ResponseField('cover', 'string', 'Cover thumbnail URL; null when none.', required: false, nullable: true, example: 'https://cdn.khyyal.com/media/41/conversions/cover-cover-thumb.jpg')]
    #[ResponseField('images', 'string[]', 'Gallery thumbnail URLs in display order.', required: false, example: ['https://cdn.khyyal.com/media/42/conversions/photo-gallery-thumb.jpg'])]
    #[ResponseField('services', 'object[]', 'Overview of the center\'s active services.', required: false)]
    #[ResponseField('services[].id', 'integer', 'Service ID.', example: 31)]
    #[ResponseField('services[].slug', 'string', 'Service slug; use it on `GET /api/v1/clients/centers/{center}/services/{slug}`.', example: 'beginner-ride')]
    #[ResponseField('services[].type', 'string', 'Service type;', example: 'recreation_riding')]
    #[ResponseField('services[].name', 'string', 'Service name in the request locale.', example: 'Beginner ride')]
    #[ResponseField('services[].cover', 'string', 'Cover thumbnail URL; null when none.', nullable: true, example: 'https://cdn.khyyal.com/media/50/conversions/cover-cover-thumb.jpg')]
    #[ResponseField('services[].start_price', 'integer', 'Lowest price option in halalas; null when none.', nullable: true, example: 15000)]
    #[ResponseField('services[].display_price', 'object', 'Set only while an automatic offer lowers the start price: `original`, `discounted`, `discount_amount` (halalas), `badge`.', nullable: true, example: ['original' => 15000, 'discounted' => 12750, 'discount_amount' => 2250, 'badge' => '-15%'])]
    #[ResponseField('ratings', 'object', 'Present only with `include=ratings`.', required: false)]
    #[ResponseField('ratings.summary.average', 'number', 'Average stars (2 decimals); null without ratings.', nullable: true, example: 4.6)]
    #[ResponseField('ratings.summary.count', 'integer', 'Number of ratings.', example: 18)]
    #[ResponseField('ratings.data', 'object[]', 'The 10 highest ratings, most stars first, then newest (`CenterRatingResource`).')]
    public function toArray(Request $request): array
    {
        $center = $this->resource;
        $distance = $center->getAttribute('distance_km');
        $prices = $center->getAttribute('service_display_prices') ?? [];

        return [
            'id' => $center->id,
            'slug' => $center->slug,
            'name' => $center->name,
            'description' => $center->description,
            'address' => $center->address,
            'city' => new CityResource($this->whenLoaded('city')),
            'location' => [
                'lat' => $center->lat,
                'lng' => $center->lng,
            ],
            'contact_phone' => $center->contact_phone,
            'distance_km' => $distance !== null ? round((float) $distance, 2) : null,
            'tags' => $this->whenLoaded('tags', fn () => $center->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])),
            'logo' => $this->whenLoaded('media', fn () => $this->mediaUrl('logo', 'thumb')),
            'cover' => $this->whenLoaded('media', fn () => $this->mediaUrl('cover', 'cover-thumb')),
            'images' => $this->whenLoaded('media', fn () => $center->media
                ->where('collection_name', 'images')
                ->map(fn ($media) => $media->getFullUrl('gallery-thumb'))
                ->values()),
            'services' => $this->whenLoaded('services', fn () => $center->services->map(fn ($service) => [
                'id' => $service->id,
                'slug' => $service->slug,
                'type' => $service->type,
                'name' => $service->name,
                'cover' => $service->getFirstMedia('cover')?->getFullUrl('cover-thumb'),
                'start_price' => $service->start_price !== null ? (int) $service->start_price : null,
                'display_price' => $prices[$service->id] ?? null,
            ])->values()),
            'ratings' => $this->whenLoaded('ratings', fn () => [
                'summary' => [
                    'average' => $center->rating_avg !== null ? round((float) $center->rating_avg, 2) : null,
                    'count' => (int) $center->ratings_count,
                ],
                'data' => CenterRatingResource::collection($center->ratings),
            ]),
        ];
    }

    private function mediaUrl(string $collection, string $conversion): ?string
    {
        return $this->resource->media->firstWhere('collection_name', $collection)?->getFullUrl($conversion);
    }
}
