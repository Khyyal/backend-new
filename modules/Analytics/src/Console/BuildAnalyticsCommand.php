<?php

namespace Modules\Analytics\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Analytics\Actions\BuildClientActivity;
use Modules\Analytics\Actions\BuildDailyCenterStats;
use Modules\Analytics\Actions\BuildDailyPlatformStats;

/**
 * Rebuilds the analytics rollups. Without options it restates the last
 * `analytics.restate_days` days up to yesterday; pass --from/--to to backfill.
 */
class BuildAnalyticsCommand extends Command
{
    protected $signature = 'analytics:build
        {--from= : First UTC date (Y-m-d) to rebuild}
        {--to= : Last UTC date (Y-m-d) to rebuild, at most yesterday (default: yesterday)}';

    protected $description = 'Rebuild the platform analytics rollups';

    public function handle(BuildDailyCenterStats $centerStats, BuildDailyPlatformStats $platformStats, BuildClientActivity $clientActivity): int
    {
        $yesterday = CarbonImmutable::yesterday();
        $to = $this->option('to') ? CarbonImmutable::parse($this->option('to'))->startOfDay() : $yesterday;
        $from = $this->option('from')
            ? CarbonImmutable::parse($this->option('from'))->startOfDay()
            : $to->subDays(max(1, (int) config('analytics.restate_days')) - 1);

        if ($to->greaterThan($yesterday)) {
            $this->error('--to must be yesterday or earlier; today is still in progress.');

            return self::INVALID;
        }

        if ($from->greaterThan($to)) {
            $this->error('--from must not be after --to.');

            return self::INVALID;
        }

        $days = 0;

        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $centerStats->execute($date);
            $platformStats->execute($date);
            $days++;
        }

        $clientActivity->execute();

        $this->line(sprintf('Analytics rebuilt for %d day(s), %s to %s.', $days, $from->toDateString(), $to->toDateString()));

        return self::SUCCESS;
    }
}
