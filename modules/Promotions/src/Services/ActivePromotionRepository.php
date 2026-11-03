<?php

namespace Modules\Promotions\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Promotions\Enums\PromotionStatus;
use Modules\Promotions\Enums\PromotionTrigger;
use Modules\Promotions\Models\Promotion;

/**
 * Cached shortlist of switched-on automatic promotions per center.
 *
 * The cache holds every active promotion regardless of its date window or
 * schedule; the window is applied in memory per call, so a promotion starting
 * or ending never leaves stale results. Any write bumps a version key, which
 * retires every cached list at once.
 */
class ActivePromotionRepository
{
    private const VERSION_KEY = 'promotions:version';

    private const TTL_SECONDS = 300;

    /**
     * Promotions that apply right now at a center: platform-wide ones plus the center's own.
     *
     * @return Collection<int, Promotion>
     */
    public function liveFor(int $centerId, CarbonInterface $at): Collection
    {
        return $this->shortlistFor($centerId)
            ->filter(fn (Promotion $promotion): bool => $promotion->isActiveAt($at) && $promotion->isScheduledAt($at))
            ->values();
    }

    /**
     * @return Collection<int, Promotion>
     */
    public function shortlistFor(int $centerId): Collection
    {
        $key = $this->key("center:{$centerId}");
        $cached = Cache::get($key);

        if ($cached instanceof Collection) {
            return $cached;
        }

        $shortlist = Promotion::query()
            ->where('status', PromotionStatus::Active->value)
            ->where('trigger', PromotionTrigger::Automatic->value)
            ->where(fn (Builder $query) => $query->whereNull('center_id')->orWhere('center_id', $centerId))
            ->with(['targets', 'schedules'])
            ->get();

        Cache::put($key, $shortlist, self::TTL_SECONDS);

        return $shortlist;
    }

    public function flush(): void
    {
        Cache::add(self::VERSION_KEY, 1);
        Cache::increment(self::VERSION_KEY);
    }

    private function key(string $suffix): string
    {
        Cache::add(self::VERSION_KEY, 1);

        return 'promotions:active:v'.(int) Cache::get(self::VERSION_KEY, 1).':'.$suffix;
    }
}
