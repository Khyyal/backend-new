<?php

namespace Modules\Search\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Search\Data\SearchHit;

/**
 * @mixin SearchHit
 */
class SearchHitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SearchHit $hit */
        $hit = $this->resource;

        return $hit->toArray();
    }
}
