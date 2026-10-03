<?php

namespace Modules\Promotion\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Promotion\Models\Discount;

trait HasDiscounts
{
    public function discounts(): MorphMany
    {
        return $this->morphMany(Discount::class, 'owner');
    }
}
