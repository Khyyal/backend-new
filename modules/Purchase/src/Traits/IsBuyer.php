<?php

namespace Modules\Purchase\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Purchase\Models\Purchase;

trait IsBuyer
{
    public function purchases(): MorphMany
    {
        return $this->morphMany(Purchase::class, 'buyer');
    }
}
