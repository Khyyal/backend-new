<?php

namespace Modules\Homepage\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Homepage\Enums\BannerActionType;
use Modules\Homepage\Enums\PublishStatus;
use Modules\Homepage\Models\Banner;
use Modules\Support\Http\Resources\MediaResource;

/**
 * @property Banner $resource
 */
class BannerResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Banner ID.', example: 5)]
    #[ResponseField('home_section_id', 'integer', 'Banner carousel section; null when unattached.', nullable: true, example: 2)]
    #[ResponseField('title', 'object', 'Title. Keyed by locale; empty object when unset.')]
    #[ResponseField('title.en', 'string', 'English.', required: false, example: 'Summer rides')]
    #[ResponseField('title.ar', 'string', 'Arabic.', required: false, example: 'ركوب الصيف')]
    #[ResponseField('subtitle', 'object', 'Subtitle. Keyed by locale; empty object when unset.')]
    #[ResponseField('subtitle.en', 'string', 'English.', required: false, example: 'Up to 20% off')]
    #[ResponseField('subtitle.ar', 'string', 'Arabic.', required: false, example: 'خصم حتى 20%')]
    #[ResponseField('cta_label', 'object', 'Button label. Keyed by locale; empty object when unset.')]
    #[ResponseField('cta_label.en', 'string', 'English.', required: false, example: 'Book now')]
    #[ResponseField('cta_label.ar', 'string', 'Arabic.', required: false, example: 'احجز الآن')]
    #[ResponseField('action_type', 'string', 'What a tap does.', enum: BannerActionType::class, example: 'promotion')]
    #[ResponseField('action_target_id', 'integer', 'Target ID for center/service/promotion/campaign actions.', nullable: true, example: 12)]
    #[ResponseField('action_url', 'string', 'URL for the `url` action.', nullable: true, example: 'No-example')]
    #[ResponseField('center_id', 'integer', 'Center the banner is tied to (hidden when the center is hidden or outside the radius).', nullable: true, example: 'No-example')]
    #[ResponseField('sort_order', 'integer', 'Order inside the carousel (ascending).', example: 0)]
    #[ResponseField('status', 'string', 'Publish status.', enum: PublishStatus::class, example: 'published')]
    #[ResponseField('is_live', 'boolean', 'Published and within its schedule now.', example: true)]
    #[ResponseField('starts_at', 'string', 'Schedule start (ISO-8601).', nullable: true, example: '2026-06-01T00:00:00+00:00')]
    #[ResponseField('ends_at', 'string', 'Schedule end (ISO-8601).', nullable: true, example: 'No-example')]
    #[ResponseField('images', 'object', 'Image per locale. Present when media is loaded.', required: false)]
    #[ResponseField('images.en', 'object', 'Image for `en` (`MediaResource`); null when none (the other locale is used).', nullable: true, example: ['id' => '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90', 'status' => 'attached', 'url' => 'https://cdn.khyyal.com/media/60/banner.jpg', 'filename' => 'banner.jpg', 'mime_type' => 'image/jpeg', 'size' => 284233])]
    #[ResponseField('images.ar', 'object', 'Image for `ar` (`MediaResource`); null when none (the other locale is used).', nullable: true, example: ['id' => '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90', 'status' => 'attached', 'url' => 'https://cdn.khyyal.com/media/60/banner.jpg', 'filename' => 'banner.jpg', 'mime_type' => 'image/jpeg', 'size' => 284233])]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('updated_at', 'string', 'Last update time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
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
