<?php

namespace Modules\Promotions\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Centers\Enums\CenterStatus;
use Modules\Promotions\Enums\TargetMode;
use Modules\Promotions\Enums\TargetType;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Models\PromotionTarget;
use Modules\Services\Models\Service;
use Modules\Support\Enums\ActivationStatus;

/**
 * The bookable services a promotion covers, as a query. Mirrors
 * Promotion::appliesToLine(): include targets widen the set (any match),
 * exclude targets always remove a service, and with no include targets the
 * promotion covers every service of its center (or every service, if
 * platform-wide).
 */
class PromotionServiceFinder
{
    public function query(Promotion $promotion): Builder
    {
        $promotion->loadMissing('targets');

        $query = Service::query()
            ->where('services.status', ActivationStatus::ACTIVE->value)
            ->whereHas('center', fn (Builder $query) => $query->where('status', CenterStatus::VISIBLE->value));

        if ($promotion->center_id !== null) {
            $query->where('services.center_id', $promotion->center_id);
        }

        $includes = $promotion->targets->filter(fn (PromotionTarget $target): bool => $target->mode === TargetMode::Include);
        $excludes = $promotion->targets->filter(fn (PromotionTarget $target): bool => $target->mode === TargetMode::Exclude);

        if ($includes->isNotEmpty()) {
            $query->where(function (Builder $query) use ($includes): void {
                foreach ($includes->groupBy(fn (PromotionTarget $target): string => $target->target_type->value) as $type => $targets) {
                    $query->orWhere(fn (Builder $inner) => $this->constrain($inner, TargetType::from($type), $targets->pluck('target_id')->all()));
                }
            });
        }

        foreach ($excludes->groupBy(fn (PromotionTarget $target): string => $target->target_type->value) as $type => $targets) {
            $query->whereNot(fn (Builder $inner) => $this->constrain($inner, TargetType::from($type), $targets->pluck('target_id')->all()));
        }

        return $query;
    }

    /**
     * @param  list<int>  $ids
     */
    private function constrain(Builder $query, TargetType $type, array $ids): void
    {
        match ($type) {
            TargetType::Center => $query->whereIn('services.center_id', $ids),
            TargetType::Service => $query->whereIn('services.id', $ids),
        };
    }
}
