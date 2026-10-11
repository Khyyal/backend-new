<?php

namespace Modules\Promotion\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Promotion\Models\Discount;

trait HasDiscounts
{
    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
    }
}
