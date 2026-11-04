<?php

namespace Modules\Purchase\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Purchase\Actions\GenerateCenterPayouts;

class GenerateCenterPayoutsCommand extends Command
{
    protected $signature = 'purchase:generate-payouts {--cutoff= : Include entries available up to this UTC date-time (default: start of today)}';

    protected $description = 'Create the weekly pending payouts of what the platform owes each center';

    public function handle(GenerateCenterPayouts $generate): int
    {
        $cutoff = $this->option('cutoff')
            ? Carbon::parse($this->option('cutoff'))
            : now()->startOfDay();

        $payouts = $generate->execute($cutoff);

        $this->line(sprintf('%d payout(s) created, %d halalas in total.', $payouts->count(), $payouts->sum('amount')));

        return self::SUCCESS;
    }
}
