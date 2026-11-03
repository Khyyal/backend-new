<?php

namespace Modules\Promotions\Http\Requests\Concerns;

use Modules\Centers\Models\Center;

/**
 * Promotion requests serve both the admin API and the center API
 * (`centers/{center}/promotions`), where the center is fixed by the route.
 */
trait ResolvesPromotionContext
{
    protected function routeCenter(): ?Center
    {
        $center = $this->route('center');

        return $center instanceof Center ? $center : null;
    }

    protected function isCenterContext(): bool
    {
        return $this->routeCenter() !== null;
    }
}
