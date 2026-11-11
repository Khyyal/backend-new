<?php

namespace Modules\Analytics\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Analytics\Actions\BuildDailyCenterStats;
use Modules\Analytics\Actions\BuildDailyPlatformStats;
use Modules\Settlements\Events\CenterFinancialBaselineConfirmed;

/**
 * A baseline corrects the totals of its cutoff day, which is usually older
 * than the days the nightly build restates, so that day is rebuilt here.
 * Today is left to the nightly build, like every day still in progress.
 */
class RebuildBaselineCutoffDay implements ShouldHandleEventsAfterCommit, ShouldQueue
{
    public function __construct(
        private BuildDailyCenterStats $centerStats,
        private BuildDailyPlatformStats $platformStats,
    ) {}

    public function handle(CenterFinancialBaselineConfirmed $event): void
    {
        $day = CarbonImmutable::parse($event->baseline->cutoff_at)->startOfDay();

        if ($day->greaterThanOrEqualTo(CarbonImmutable::today())) {
            return;
        }

        $this->centerStats->execute($day);
        $this->platformStats->execute($day);
    }
}
