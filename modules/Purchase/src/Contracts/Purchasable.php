<?php

namespace Modules\Purchase\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Purchasable
{
    public function purchaseItems(): MorphMany;
}
