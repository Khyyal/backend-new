<?php

namespace Modules\Homepage\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Homepage\Enums\BannerActionType;
use Modules\Homepage\Models\Banner;

/**
 * A banner as the apps show it: texts and artwork in the request locale, and what tapping it opens.
 *
 * @property Banner $resource
 */
class BannerResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Banner ID.', example: 5)]
    #[ResponseField('title', 'string', 'Title in the request locale.', example: 'Summer rides')]
    #[ResponseField('subtitle', 'string', 'Subtitle in the request locale.', nullable: true, example: 'Up to 20% off')]
    #[ResponseField('cta_label', 'string', 'Button label in the request locale.', nullable: true, example: 'Book now')]
    #[ResponseField('image', 'object', 'Image for the request locale (falls back to the other): `url`, `wide_url`, `thumb_url`; null when none.', nullable: true, example: ['url' => 'https://cdn.khyyal.com/media/60/banner.jpg', 'wide_url' => 'https://cdn.khyyal.com/media/60/conversions/banner-banner-wide.jpg', 'thumb_url' => 'https://cdn.khyyal.com/media/60/conversions/banner-banner-thumb.jpg'])]
    #[ResponseField('action', 'object', 'Tap action: `{type: none}`, `{type: url, url}`, or `{type: center|service|promotion|campaign, id}`.', example: ['type' => 'promotion', 'id' => 12])]
    public function toArray(Request $request): array
    {
        $banner = $this->resource;
        $image = $banner->imageFor(app()->getLocale());

        return [
            'id' => $banner->id,
            'title' => $banner->title,
            'subtitle' => $banner->subtitle,
            'cta_label' => $banner->cta_label,
            'image' => $image === null ? null : [
                'url' => $image->getFullUrl(),
                'wide_url' => $image->hasGeneratedConversion('banner-wide') ? $image->getFullUrl('banner-wide') : $image->getFullUrl(),
                'thumb_url' => $image->hasGeneratedConversion('banner-thumb') ? $image->getFullUrl('banner-thumb') : $image->getFullUrl(),
            ],
            'action' => match ($banner->action_type) {
                BannerActionType::None => ['type' => BannerActionType::None->value],
                BannerActionType::Url => ['type' => BannerActionType::Url->value, 'url' => $banner->action_url],
                default => ['type' => $banner->action_type->value, 'id' => $banner->action_target_id],
            },
        ];
    }
}
