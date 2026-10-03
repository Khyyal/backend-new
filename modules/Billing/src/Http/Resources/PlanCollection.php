<?php

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PlanCollection extends ResourceCollection
{
    public $collects = PlanResource::class;
}
