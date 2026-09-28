<?php

namespace Modules\Promotion\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class DiscountCollection extends ResourceCollection
{
    public $collects = DiscountResource::class;
}
