<?php

namespace Modules\Homepage\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Homepage\Enums\BannerActionType;
use Modules\Homepage\Models\Banner;

/**
 * A banner as the apps show it: texts and artwork in the request locale, and what tapping it opens.
 *
 * @property Banner $resource
 */
class BannerResource extends JsonResource
{
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
