<?php

namespace Modules\Purchase\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Purchase\Models\PurchaseItem;

trait IsPurchasable
{
    public function purchaseItems(): MorphMany
    {
        return $this->morphMany(PurchaseItem::class, 'purchasable');
    }
}
