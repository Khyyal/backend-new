<?php

namespace Modules\Promotions\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Promotions\Services\CounterReconciler;

class ReconcilePromotionCountersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(CounterReconciler $reconciler): void
    {
        $reconciler->reconcile();
    }
}
