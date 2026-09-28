<?php

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class LimitCollection extends ResourceCollection
{
    public $collects = LimitResource::class;
}
