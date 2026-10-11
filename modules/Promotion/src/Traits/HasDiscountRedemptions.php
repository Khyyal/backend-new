<?php

namespace Modules\Promotion\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Promotion\Models\DiscountRedemption;

trait HasDiscountRedemptions
{
    public function discountRedemptions(): MorphMany
    {
        return $this->morphMany(DiscountRedemption::class, 'discountable');
    }
}
