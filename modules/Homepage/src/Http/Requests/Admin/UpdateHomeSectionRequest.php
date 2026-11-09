<?php

namespace Modules\Homepage\Http\Requests\Admin;

use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Homepage\Enums\PublishStatus;

#[BodyParam('key', 'string', 'Unique key: letters, numbers, dashes, underscores (max 100).', required: false, example: 'summer-deals')]
#[BodyParam('type', 'string', 'Prohibited: the type cannot change.', required: false, example: 'No-example')]
#[BodyParam('title', 'object', 'Title keyed by locale.', required: false, nullable: true, example: ['en' => 'Summer deals', 'ar' => 'عروض الصيف'])]
#[BodyParam('title.en', 'string', 'English (max 255).', required: false, nullable: true, example: 'Summer deals')]
#[BodyParam('title.ar', 'string', 'Arabic (max 255).', required: false, nullable: true, example: 'عروض الصيف')]
#[BodyParam('subtitle', 'object', 'Subtitle keyed by locale.', required: false, nullable: true, example: ['en' => 'Limited time', 'ar' => 'لفترة محدودة'])]
#[BodyParam('subtitle.en', 'string', 'English (max 500).', required: false, nullable: true, example: 'Limited time')]
#[BodyParam('subtitle.ar', 'string', 'Arabic (max 500).', required: false, nullable: true, example: 'لفترة محدودة')]
#[BodyParam('sort_order', 'integer', 'Order, -100000 … 100000 (ascending).', required: false, example: 0)]
#[BodyParam('status', 'string', 'Publish status. Default `draft`.', required: false, enum: PublishStatus::class, example: 'published')]
#[BodyParam('starts_at', 'string', 'Schedule start (date-time); null for no start.', required: false, nullable: true, example: '2026-06-01T00:00:00Z')]
#[BodyParam('ends_at', 'string', 'Schedule end, after `starts_at`; null for open-ended.', required: false, nullable: true, example: '2026-08-31T23:59:59Z')]
#[BodyParam('config', 'object', 'Options. Only `limit`, plus `radius_km` for center, service, banner and top-centers sections. Keys are merged into the stored config; send a key as null to remove it.', required: false, nullable: true, example: ['limit' => 10, 'radius_km' => 50])]
#[BodyParam('config.limit', 'integer', 'Items shown on the home page, 1-50. Default 10.', required: false, example: 10)]
#[BodyParam('config.radius_km', 'number', 'Hide content farther than this from the client (0.1-1000 km).', required: false, nullable: true, example: 50)]
class UpdateHomeSectionRequest extends StoreHomeSectionRequest
{
    protected bool $isUpdate = true;
}
