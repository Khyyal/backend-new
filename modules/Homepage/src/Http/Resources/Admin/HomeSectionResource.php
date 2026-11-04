<?php

namespace Modules\Homepage\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
