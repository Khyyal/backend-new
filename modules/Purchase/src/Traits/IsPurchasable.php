<?php

namespace Modules\Purchase\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Purchase\Models\PurchaseItem;

trait IsPurchasable
{
    public function purchaseItems(): MorphMany
    {
        return $this->morphMany(PurchaseItem::class, 'purchasable');
    }

    /**
     * The purchase item that sold this record (the latest one).
     */
    public function purchaseItem(): MorphOne
    {
        return $this->morphOne(PurchaseItem::class, 'purchasable')->latestOfMany();
    }
}
