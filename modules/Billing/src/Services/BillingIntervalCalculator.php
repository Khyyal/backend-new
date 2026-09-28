<?php

namespace Modules\Billing\Services;

use Carbon\Carbon;
use DateTimeInterface;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Models\Plan;

class BillingIntervalCalculator
{
    public function nextBillingDate(DateTimeInterface $from, BillingInterval $interval, int $count = 1): Carbon
    {
        $date = Carbon::instance($from);

        return match ($interval) {
            BillingInterval::Monthly => $date->copy()->addMonthsNoOverflow($count),
            BillingInterval::Yearly => $date->copy()->addYears($count),
        };
    }

    public function trialEndsAt(Plan $plan, ?DateTimeInterface $from = null): ?Carbon
    {
        $trialDays = (int) ($plan->trial_days ?? 0);

        if ($trialDays <= 0) {
            return null;
        }

        $start = $from !== null ? Carbon::instance($from) : Carbon::now();

        return $start->copy()->addDays($trialDays);
    }
}
