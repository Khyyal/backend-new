<?php

namespace Modules\Promotion\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class CouponCollection extends ResourceCollection
{
    public $collects = CouponResource::class;
}
