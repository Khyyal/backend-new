<?php

namespace Modules\Purchase\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Buyer
{
    public function purchases(): MorphMany;
}
