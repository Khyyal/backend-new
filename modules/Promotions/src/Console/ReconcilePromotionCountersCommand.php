<?php

namespace Modules\Promotions\Console;

use Illuminate\Console\Command;
use Modules\Promotions\Services\CounterReconciler;

class ReconcilePromotionCountersCommand extends Command
{
    protected $signature = 'promotions:reconcile {--dry-run : Report drift without correcting it}';

    protected $description = 'Compare promotion and coupon usage counters with the redemption ledger and correct any drift';

    public function handle(CounterReconciler $reconciler): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fixed = $reconciler->reconcile($dryRun);

        foreach ($fixed as $table => $count) {
            $this->line(sprintf('%-26s %d %s', $table, $count, $dryRun ? 'would be corrected' : 'corrected'));
        }

        return self::SUCCESS;
    }
}
