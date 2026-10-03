<?php

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class SubscriptionCollection extends ResourceCollection
{
    public $collects = SubscriptionResource::class;
}
