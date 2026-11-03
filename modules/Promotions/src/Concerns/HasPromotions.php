<?php

namespace Modules\Promotions\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Promotions\Models\Promotion;

/**
 * For a center: the promotions it owns (platform-wide ones are not included).
 */
trait HasPromotions
{
    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }
}
