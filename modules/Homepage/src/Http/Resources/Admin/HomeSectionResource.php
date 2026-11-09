<?php

namespace Modules\Homepage\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Homepage\Enums\HomeSectionType;
use Modules\Homepage\Enums\ItemableType;
use Modules\Homepage\Enums\PublishStatus;
use Modules\Homepage\Models\HomeSection;
use Modules\Homepage\Models\HomeSectionItem;

/**
 * A section as admins edit it. Loaded items carry a short summary of the picked record
 * (see `ItemCatalog::summaries()`), so the picker can show them without extra requests.
 *
 * @property HomeSection $resource
 */
class HomeSectionResource extends JsonResource
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $itemSummaries = [];

    /**
     * @param  array<string, array<string, mixed>>  $summaries  keyed by "type:id"
     */
    public function withItemSummaries(array $summaries): static
    {
        $this->itemSummaries = $summaries;

        return $this;
    }

    #[ResponseField('id', 'integer', 'Section ID.', example: 2)]
    #[ResponseField('key', 'string', 'Unique key; used by `GET /api/v1/clients/home/sections/{key}`.', example: 'summer-deals')]
    #[ResponseField('type', 'string', 'Section type, fixed at creation.', enum: HomeSectionType::class, example: 'curated_promotions')]
    #[ResponseField('item_type', 'string', 'Item kind a curated section holds; null for `banner_carousel` and `top_centers`.', nullable: true, enum: ItemableType::class, example: 'promotion')]
    #[ResponseField('title', 'object', 'Title. Keyed by locale; empty object when unset.')]
    #[ResponseField('title.en', 'string', 'English.', required: false, example: 'Summer deals')]
    #[ResponseField('title.ar', 'string', 'Arabic.', required: false, example: 'عروض الصيف')]
    #[ResponseField('subtitle', 'object', 'Subtitle. Keyed by locale; empty object when unset.')]
    #[ResponseField('subtitle.en', 'string', 'English.', required: false, example: 'Limited time')]
    #[ResponseField('subtitle.ar', 'string', 'Arabic.', required: false, example: 'لفترة محدودة')]
    #[ResponseField('sort_order', 'integer', 'Display order (ascending).', example: 1)]
    #[ResponseField('status', 'string', 'Publish status.', enum: PublishStatus::class, example: 'published')]
    #[ResponseField('starts_at', 'string', 'Schedule start (ISO-8601).', nullable: true, example: 'No-example')]
    #[ResponseField('ends_at', 'string', 'Schedule end (ISO-8601).', nullable: true, example: 'No-example')]
    #[ResponseField('is_live', 'boolean', 'Published and within its schedule now.', example: true)]
    #[ResponseField('config', 'object', '`limit` (items shown, default 10) and, for center/service/banner/top-centers sections, `radius_km`. Empty object when unset.', example: ['limit' => 10, 'radius_km' => 50])]
    #[ResponseField('items', 'object[]', 'Picked items in display order. Present on show and after changes.', required: false)]
    #[ResponseField('items[].type', 'string', 'Item kind.', enum: ItemableType::class, example: 'promotion')]
    #[ResponseField('items[].id', 'integer', 'Item ID.', example: 12)]
    #[ResponseField('items[].sort_order', 'integer', 'Display order.', example: 0)]
    #[ResponseField('items[].starts_at', 'string', 'Item window start (ISO-8601).', nullable: true, example: 'No-example')]
    #[ResponseField('items[].ends_at', 'string', 'Item window end (ISO-8601).', nullable: true, example: 'No-example')]
    #[ResponseField('items[].label', 'string', 'Item name for the admin UI.', nullable: true, example: '15% off rides')]
    #[ResponseField('items[].subtitle', 'string', 'Secondary text (e.g. center name).', nullable: true, example: 'Al Fursan Stables')]
    #[ResponseField('items[].thumb', 'string', 'Thumbnail URL.', nullable: true, example: 'No-example')]
    #[ResponseField('items[].exists', 'boolean', 'False when the record was deleted.', example: true)]
    #[ResponseField('items[].is_available', 'boolean', 'Visible to clients right now.', example: true)]
    #[ResponseField('items_count', 'integer', 'Picked items.', required: false, example: 3)]
    #[ResponseField('banners_count', 'integer', 'Attached banners (carousels).', required: false, example: 0)]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('updated_at', 'string', 'Last update time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    public function toArray(Request $request): array
    {
        $section = $this->resource;

        return [
            'id' => $section->id,
            'key' => $section->key,
            'type' => $section->type->value,
            'item_type' => $section->type->itemType()?->value,
            'title' => $section->getTranslations('title'),
            'subtitle' => $section->getTranslations('subtitle'),
            'sort_order' => $section->sort_order,
            'status' => $section->status->value,
            'starts_at' => $section->starts_at?->toIso8601String(),
            'ends_at' => $section->ends_at?->toIso8601String(),
            'is_live' => $section->isLiveAt(now()),
            'config' => $section->config ?? (object) [],
            'items' => $this->whenLoaded('items', fn () => $section->items->map(function (HomeSectionItem $item): array {
                $summary = $this->itemSummaries[$item->itemable_type->value.':'.$item->itemable_id] ?? null;

                return [
                    'type' => $item->itemable_type->value,
                    'id' => $item->itemable_id,
                    'sort_order' => $item->sort_order,
                    'starts_at' => $item->starts_at?->toIso8601String(),
                    'ends_at' => $item->ends_at?->toIso8601String(),
                    'label' => $summary['label'] ?? null,
                    'subtitle' => $summary['subtitle'] ?? null,
                    'thumb' => $summary['thumb'] ?? null,
                    'exists' => $summary !== null,
                    'is_available' => $summary['is_available'] ?? false,
                ];
            })->values()),
            'items_count' => $this->whenCounted('items'),
            'banners_count' => $this->whenCounted('banners'),
            'created_at' => $section->created_at?->toIso8601String(),
            'updated_at' => $section->updated_at?->toIso8601String(),
        ];
    }
}
