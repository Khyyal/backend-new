<?php

namespace Modules\Homepage\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Homepage\Models\Banner;
use Modules\Support\Http\Resources\MediaResource;

/**
 * @property Banner $resource
 */
class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $banner = $this->resource;

        return [
            'id' => $banner->id,
            'home_section_id' => $banner->home_section_id,
            'title' => $banner->getTranslations('title'),
            'subtitle' => $banner->getTranslations('subtitle'),
            'cta_label' => $banner->getTranslations('cta_label'),
            'action_type' => $banner->action_type->value,
            'action_target_id' => $banner->action_target_id,
            'action_url' => $banner->action_url,
            'center_id' => $banner->center_id,
            'sort_order' => $banner->sort_order,
            'status' => $banner->status->value,
            'is_live' => $banner->isLiveAt(now()),
            'starts_at' => $banner->starts_at?->toIso8601String(),
            'ends_at' => $banner->ends_at?->toIso8601String(),
            'images' => $this->whenLoaded('media', fn (): array => collect(Banner::LOCALES)->mapWithKeys(fn (string $locale): array => [
                $locale => ($media = $banner->getFirstMedia(Banner::imageCollection($locale))) !== null ? new MediaResource($media) : null,
            ])->all()),
            'created_at' => $banner->created_at?->toIso8601String(),
            'updated_at' => $banner->updated_at?->toIso8601String(),
        ];
    }
}
