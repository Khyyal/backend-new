<?php

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class FeatureCollection extends ResourceCollection
{
    public $collects = FeatureResource::class;
}
