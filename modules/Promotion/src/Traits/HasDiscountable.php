<?php

namespace Modules\Promotion\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Promotion\Models\Discount;

trait HasDiscountable
{
    public function discounts(): BelongsToMany
    {
        return $this->belongsToMany(Discount::class, 'discount_service')->withTimestamps();
    }
}
