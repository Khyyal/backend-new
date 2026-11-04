<?php

namespace Modules\Homepage\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Homepage\Enums\PublishStatus;
use Modules\Homepage\Models\HomeSection;

class HomeSectionService
{
    public function __construct(
        private readonly HomeContentCache $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?Model $actor): HomeSection
    {
        $section = HomeSection::create(array_merge(
            ['status' => PublishStatus::Draft->value],
            $data,
            ['config' => array_merge(['limit' => 10], $data['config'] ?? []), 'created_by' => $actor?->getKey()],
        ));

        return $section->refresh();
    }

    /**
     * Config keys sent are merged into the stored config; a null value removes the key.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(HomeSection $section, array $data): HomeSection
    {
        if (array_key_exists('config', $data)) {
            $data['config'] = array_filter(
                array_merge($section->config ?? [], $data['config'] ?? []),
                fn (mixed $value): bool => $value !== null,
            );
        }

        $section->update($data);

        return $section->refresh();
    }

    /**
     * Rewrites sort_order to follow the given id order; sections not listed keep their place after them.
     *
     * @param  list<int>  $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds): void {
            foreach (array_values($orderedIds) as $position => $id) {
                HomeSection::query()->whereKey($id)->update(['sort_order' => $position]);
            }

            HomeSection::query()
                ->whereNotIn('id', $orderedIds)
                ->increment('sort_order', count($orderedIds));
        });

        $this->cache->bump();
    }

    /**
     * Replaces the hand-picked items; list order becomes display order.
     *
     * @param  list<array{type: string, id: int, starts_at?: string|null, ends_at?: string|null}>  $items
     */
    public function syncItems(HomeSection $section, array $items): HomeSection
    {
        DB::transaction(function () use ($section, $items): void {
            $section->items()->delete();

            foreach (array_values($items) as $position => $item) {
                $section->items()->create([
                    'itemable_type' => $item['type'],
                    'itemable_id' => $item['id'],
                    'sort_order' => $position,
                    'starts_at' => $item['starts_at'] ?? null,
                    'ends_at' => $item['ends_at'] ?? null,
                ]);
            }
        });

        $this->cache->bump();

        return $section->load('items');
    }
}
