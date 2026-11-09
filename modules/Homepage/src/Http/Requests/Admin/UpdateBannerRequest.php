<?php

namespace Modules\Homepage\Http\Requests\Admin;

use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Homepage\Enums\BannerActionType;
use Modules\Homepage\Enums\PublishStatus;

#[BodyParam('title', 'object', 'Title keyed by locale.', required: false, example: ['en' => 'Summer rides', 'ar' => 'ركوب الصيف'])]
#[BodyParam('title.en', 'string', 'English title (max 255).', required: false, example: 'Summer rides')]
#[BodyParam('title.ar', 'string', 'Arabic title (max 255).', required: false, nullable: true, example: 'ركوب الصيف')]
#[BodyParam('subtitle', 'object', 'Subtitle keyed by locale.', required: false, nullable: true, example: ['en' => 'Up to 20% off', 'ar' => 'خصم حتى 20%'])]
#[BodyParam('subtitle.en', 'string', 'English (max 500).', required: false, nullable: true, example: 'Up to 20% off')]
#[BodyParam('subtitle.ar', 'string', 'Arabic (max 500).', required: false, nullable: true, example: 'خصم حتى 20%')]
#[BodyParam('cta_label', 'object', 'Button label keyed by locale.', required: false, nullable: true, example: ['en' => 'Book now', 'ar' => 'احجز الآن'])]
#[BodyParam('cta_label.en', 'string', 'English (max 60).', required: false, nullable: true, example: 'Book now')]
#[BodyParam('cta_label.ar', 'string', 'Arabic (max 60).', required: false, nullable: true, example: 'احجز الآن')]
#[BodyParam('home_section_id', 'integer', 'A `banner_carousel` section; null to detach.', required: false, nullable: true, example: 2)]
#[BodyParam('action_type', 'string', '`none`, `url` (needs `action_url`), or `center`/`service`/`promotion`/`campaign` (need `action_target_id`).', required: false, enum: BannerActionType::class, example: 'promotion')]
#[BodyParam('action_target_id', 'integer', 'Target ID for record actions (min 1).', required: false, nullable: true, example: 12)]
#[BodyParam('action_url', 'string', 'http(s) URL for the `url` action (max 2048).', required: false, nullable: true, example: 'https://khyyal.com/summer')]
#[BodyParam('center_id', 'integer', 'Tie the banner to a center (not deleted): hidden when the center is hidden or outside the section radius.', required: false, nullable: true, example: 8)]
#[BodyParam('sort_order', 'integer', 'Order, -100000 … 100000 (ascending).', required: false, example: 0)]
#[BodyParam('status', 'string', 'Publish status. Default `draft`.', required: false, enum: PublishStatus::class, example: 'published')]
#[BodyParam('starts_at', 'string', 'Schedule start (date-time); null for no start.', required: false, nullable: true, example: '2026-06-01T00:00:00Z')]
#[BodyParam('ends_at', 'string', 'Schedule end, after `starts_at`; null for open-ended.', required: false, nullable: true, example: '2026-08-31T23:59:59Z')]
#[BodyParam('images', 'object', 'One temporary upload UUID per locale (`en`, `ar`); null removes that locale\'s image. A locale without an image falls back to the other.', required: false, example: ['en' => '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90', 'ar' => '1c2d3e4f-5a6b-4c7d-8e9f-0a1b2c3d4e5f'])]
class UpdateBannerRequest extends StoreBannerRequest
{
    protected bool $isUpdate = true;
}
