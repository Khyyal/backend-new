<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Enums\CenterStatus;
use Modules\Support\Http\Resources\CityResource;
use Modules\Support\Http\Resources\MediaResource;

class CenterResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('name', 'string', 'Center name.', example: 'Al Fursan Stables')]
    #[ResponseField('slug', 'string', 'Unique URL slug, generated from the name.', example: 'al-fursan-stables')]
    #[ResponseField('contact_phone', 'string', 'Public contact phone.', example: '+966500000001')]
    #[ResponseField('email', 'string', 'Contact email (lower-case).', nullable: true, example: 'info@alfursan.sa')]
    #[ResponseField('email_verified_at', 'string', 'When the contact email was verified (ISO-8601); null while unverified or when the email changed.', nullable: true, example: '2026-06-01T10:00:00+00:00')]
    #[ResponseField('social_links', 'object', 'Links keyed by platform (`facebook`, `instagram`, `whatsapp`, `tiktok`, `snapchat`, `x`, `youtube`, `website`); null when unset.', nullable: true, example: ['instagram' => 'https://instagram.com/alfursan', 'website' => 'https://alfursan.sa'])]
    #[ResponseField('site_theme', 'object', 'Hex colors keyed by role (`background`, `foreground`, `primary`, `button`, `secondary`, `accent`); null when unset.', nullable: true, example: ['primary' => '#8B4513', 'background' => '#FFFFFF'])]
    #[ResponseField('custom_site_enabled', 'boolean', 'Whether the center\'s own website is enabled.', example: false)]
    #[ResponseField('domain', 'string', 'Custom website domain; null when none.', nullable: true, example: 'alfursan.sa')]
    #[ResponseField('status', 'string', '`visible` centers are listed in the client app.', enum: CenterStatus::class, example: 'visible')]
    #[ResponseField('state', 'string', 'Account state: `pending` (awaiting approval), `active`, `blocked`. Only active centers can use center-scoped features.', enum: CenterState::class, example: 'active')]
    #[ResponseField('points', 'integer', 'Popularity score used for the `most_popular` order.', example: 120)]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('deleted_at', 'string', 'Soft-delete time (ISO-8601); null when not deleted.', nullable: true, example: 'No-example')]
    #[ResponseField('address', 'string', 'Street address.', nullable: true, example: 'King Fahd Rd, Al Olaya')]
    #[ResponseField('lat', 'number', 'Latitude.', nullable: true, example: 24.7136)]
    #[ResponseField('lng', 'number', 'Longitude.', nullable: true, example: 46.6753)]
    #[ResponseField('description', 'string', 'Free-text description.', nullable: true, example: 'Riding lessons and trail rides for all levels.')]
    #[ResponseField('city', 'object', 'The city (`CityResource`). Present when loaded.', required: false)]
    #[ResponseField('city.id', 'integer', 'City ID.', example: 1)]
    #[ResponseField('city.name', 'string', 'City name in the request locale.', example: 'Riyadh')]
    #[ResponseField('city.radius', 'integer', 'Coverage radius in km.', example: 40)]
    #[ResponseField('tags', 'object[]', 'Tags of the center. Present when loaded.', required: false)]
    #[ResponseField('tags[].id', 'integer', 'Tag ID.', example: 4)]
    #[ResponseField('tags[].name', 'string', 'Tag name in the request locale.', example: 'Family friendly')]
    #[ResponseField('logo', 'object', 'Logo (`MediaResource`); null when none. Present when media is loaded.', required: false, nullable: true)]
    #[ResponseField('logo.id', 'string', 'Media UUID.', example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
    #[ResponseField('logo.status', 'string', 'Media lifecycle state.', enum: ['temporary', 'attached'], example: 'attached')]
    #[ResponseField('logo.url', 'string', 'Public URL of the original file.', example: 'https://cdn.khyyal.com/media/12/photo.jpg')]
    #[ResponseField('logo.filename', 'string', 'Stored file name.', example: 'photo.jpg')]
    #[ResponseField('logo.mime_type', 'string', 'MIME type.', example: 'image/jpeg')]
    #[ResponseField('logo.size', 'integer', 'File size in bytes.', example: 184233)]
    #[ResponseField('cover', 'object', 'Cover image (`MediaResource`); null when none. Present when media is loaded.', required: false, nullable: true)]
    #[ResponseField('cover.id', 'string', 'Media UUID.', example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
    #[ResponseField('cover.status', 'string', 'Media lifecycle state.', enum: ['temporary', 'attached'], example: 'attached')]
    #[ResponseField('cover.url', 'string', 'Public URL of the original file.', example: 'https://cdn.khyyal.com/media/12/photo.jpg')]
    #[ResponseField('cover.filename', 'string', 'Stored file name.', example: 'photo.jpg')]
    #[ResponseField('cover.mime_type', 'string', 'MIME type.', example: 'image/jpeg')]
    #[ResponseField('cover.size', 'integer', 'File size in bytes.', example: 184233)]
    #[ResponseField('images', 'object[]', 'Gallery images in display order (max 10). Present when media is loaded.', required: false, nullable: false)]
    #[ResponseField('images[].id', 'string', 'Media UUID.', example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
    #[ResponseField('images[].status', 'string', 'Media lifecycle state.', enum: ['temporary', 'attached'], example: 'attached')]
    #[ResponseField('images[].url', 'string', 'Public URL of the original file.', example: 'https://cdn.khyyal.com/media/12/photo.jpg')]
    #[ResponseField('images[].filename', 'string', 'Stored file name.', example: 'photo.jpg')]
    #[ResponseField('images[].mime_type', 'string', 'MIME type.', example: 'image/jpeg')]
    #[ResponseField('images[].size', 'integer', 'File size in bytes.', example: 184233)]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'contact_phone' => $this->contact_phone,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'social_links' => $this->social_links,
            'site_theme' => $this->site_theme,
            'custom_site_enabled' => $this->custom_site_enabled,
            'domain' => $this->domain,
            'status' => $this->status,
            'state' => $this->state,
            'points' => $this->points,
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'address' => $this->address,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'description' => $this->description,
            'city' => new CityResource($this->whenLoaded('city')),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])),
            'logo' => $this->whenLoaded('media', fn () => $this->singleMedia('logo')),
            'cover' => $this->whenLoaded('media', fn () => $this->singleMedia('cover')),
            'images' => $this->whenLoaded('media', fn () => MediaResource::collection(
                $this->media->where('collection_name', 'images')->sortBy('order_column')->values()
            )),
        ];
    }

    private function singleMedia(string $collection): ?MediaResource
    {
        $media = $this->media->firstWhere('collection_name', $collection);

        return $media === null ? null : new MediaResource($media);
    }
}
