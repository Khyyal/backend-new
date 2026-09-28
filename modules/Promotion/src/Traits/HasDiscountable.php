<?php

namespace Modules\Promotion\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Modules\Promotion\Models\Discount;
use Modules\Promotion\Models\DiscountRedemption;

trait HasDiscountable
{
    public function discountRedemptions(): MorphMany
    {
        return $this->morphMany(DiscountRedemption::class, 'discountable');
    }

    public function discounts(): MorphToMany
    {
        return $this->morphToMany(
            Discount::class,
            'discountable',
            'discountables'
        )->withTimestamps();
    }
}
