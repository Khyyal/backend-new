<?php

namespace Modules\Homepage\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Models\Center;
use Modules\Homepage\Enums\ItemableType;
use Modules\Homepage\Models\HomeSectionItem;
use Modules\Promotions\Models\Campaign;
use Modules\Promotions\Models\Promotion;
use Modules\Services\Models\Service;
use Modules\Support\Enums\ActivationStatus;

/**
 * What admins pick curated items from: searchable candidates of one item type, and short
 * summaries (label, subtitle, thumbnail, whether clients can see it now) of picked items.
 *
 * @phpstan-type ItemSummary array{type: string, id: int, label: string|null, subtitle: string|null, thumb: string|null, is_available: bool}
 */
class ItemCatalog
{
    /**
     * @return LengthAwarePaginator<int, ItemSummary>
     */
    public function candidates(ItemableType $type, ?string $search, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $search = $search !== null ? trim($search) : '';

        $paginator = $this->query($type)
            ->when($search !== '', fn (Builder $query) => $this->applySearch($query, $type, $search))
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return $paginator->through(fn (Model $model): array => $this->summarize($type, $model));
    }

    /**
     * Summaries of the given items, keyed by "type:id". Items whose record is gone are absent.
     *
     * @param  Collection<int, HomeSectionItem>  $items
     * @return array<string, ItemSummary>
     */
    public function summaries(Collection $items): array
    {
        $summaries = [];

        foreach ($items->groupBy(fn (HomeSectionItem $item): string => $item->itemable_type->value) as $type => $group) {
            $itemType = ItemableType::from($type);
            $models = $this->query($itemType)->whereKey($group->pluck('itemable_id')->all())->get();

            foreach ($models as $model) {
                $summaries[$type.':'.$model->getKey()] = $this->summarize($itemType, $model);
            }
        }

        return $summaries;
    }

    /**
     * @return Builder<Model>
     */
    private function query(ItemableType $type): Builder
    {
        return match ($type) {
            ItemableType::Center => Center::query()->with(['city', 'media']),
            ItemableType::Service => Service::query()->with(['center', 'media']),
            ItemableType::Promotion => Promotion::query()->with(['center', 'media']),
            ItemableType::Campaign => Campaign::query()->with('media'),
        };
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applySearch(Builder $query, ItemableType $type, string $search): void
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        $query->where(function (Builder $query) use ($type, $search, $like): void {
            if (ctype_digit($search)) {
                $query->orWhereKey((int) $search);
            }

            if ($type === ItemableType::Center) {
                $query->orWhere('name', 'like', $like);

                return;
            }

            // Translatable names: match any locale.
            $query->orWhere('name->en', 'like', $like)->orWhere('name->ar', 'like', $like);
        });
    }

    /**
     * @return ItemSummary
     */
    private function summarize(ItemableType $type, Model $model): array
    {
        [$label, $subtitle, $thumb, $isAvailable] = match (true) {
            $model instanceof Center => [
                $model->name,
                $model->city?->name,
                $model->getFirstMedia('logo')?->getFullUrl('thumb'),
                $this->centerIsVisible($model),
            ],
            $model instanceof Service => [
                $model->name,
                $model->center?->name,
                $model->getFirstMedia('cover')?->getFullUrl('cover-thumb'),
                $model->status === ActivationStatus::ACTIVE && $model->center !== null && $this->centerIsVisible($model->center),
            ],
            $model instanceof Promotion => [
                $model->name,
                $model->center?->name,
                $model->getFirstMedia('cover')?->getFullUrl('cover-thumb'),
                $model->isActiveAt(now()),
            ],
            $model instanceof Campaign => [
                $model->name,
                null,
                $model->getFirstMedia('cover')?->getFullUrl('cover-thumb'),
                $model->isRunningAt(now()),
            ],
        };

        return [
            'type' => $type->value,
            'id' => (int) $model->getKey(),
            'label' => $label,
            'subtitle' => $subtitle,
            'thumb' => $thumb,
            'is_available' => $isAvailable,
        ];
    }

    private function centerIsVisible(Center $center): bool
    {
        return $center->status === CenterStatus::VISIBLE && $center->state === CenterState::Active;
    }
}
