<?php

namespace Modules\Centers\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Centers\Models\Center;
use Modules\Support\Http\Resources\CityResource;

/**
 * @property Center $resource
 */
class CenterResource extends JsonResource
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
    #[ResponseField('social_links', 'object', 'Links keyed by platform (`facebook`, `instagram`, `whatsapp`, `tiktok`, `snapchat`, `x`, `youtube`, `website`); null when unset.', nullable: true, example: ['instagram' => 'https://instagram.com/alfursan', 'website' => 'https://alfursan.sa'])]
    #[ResponseField('site_theme', 'object', 'Hex colors keyed by role (`background`, `foreground`, `primary`, `button`, `secondary`, `accent`); null when unset.', nullable: true, example: ['primary' => '#8B4513', 'background' => '#FFFFFF'])]
    #[ResponseField('tags', 'object[]', 'Tags of the center. Present when loaded.', required: false)]
    #[ResponseField('tags[].id', 'integer', 'Tag ID.', example: 4)]
    #[ResponseField('tags[].name', 'string', 'Tag name in the request locale.', example: 'Family friendly')]
    #[ResponseField('rating', 'object', 'Average rating.')]
    #[ResponseField('rating.avg', 'number', 'Average stars (2 decimals); null without ratings.', nullable: true, example: 4.6)]
    #[ResponseField('rating.count', 'integer', 'Number of ratings.', example: 18)]
    #[ResponseField('logo', 'string', 'Logo thumbnail URL; null when none. Present when media is loaded.', required: false, nullable: true, example: 'https://cdn.khyyal.com/media/40/conversions/logo-thumb.jpg')]
    #[ResponseField('cover', 'string', 'Cover thumbnail URL; null when none. Present when media is loaded.', required: false, nullable: true, example: 'https://cdn.khyyal.com/media/41/conversions/cover-cover-thumb.jpg')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'slug' => $this->resource->slug,
            'name' => $this->resource->name,
            'address' => $this->resource->address,
            'city' => new CityResource($this->whenLoaded('city')),
            'description' => $this->resource->description,
            'location' => [
                'lat' => $this->resource->lat,
                'lng' => $this->resource->lng,
            ],
            'contact_phone' => $this->resource->contact_phone,
            'social_links' => $this->resource->social_links,
            'site_theme' => $this->resource->site_theme,
            'tags' => $this->whenLoaded('tags', fn () => $this->resource->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])),
            'rating' => [
                'avg' => $this->resource->rating_avg !== null ? round((float) $this->resource->rating_avg, 2) : null,
                'count' => (int) ($this->resource->ratings_count ?? 0),
            ],
            'logo' => $this->whenLoaded('media', fn () => $this->thumbUrl('logo', 'thumb')),
            'cover' => $this->whenLoaded('media', fn () => $this->thumbUrl('cover', 'cover-thumb')),
        ];
    }

    private function thumbUrl(string $collection, string $conversion): ?string
    {
        return $this->resource->media->firstWhere('collection_name', $collection)?->getFullUrl($conversion);
    }
}
