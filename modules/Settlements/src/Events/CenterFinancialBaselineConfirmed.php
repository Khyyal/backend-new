<?php

namespace Modules\Settlements\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Settlements\Models\CenterFinancialBaseline;

/**
 * Finance set or changed a center's baseline figures.
 */
class CenterFinancialBaselineConfirmed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CenterFinancialBaseline $baseline,
    ) {}
}
