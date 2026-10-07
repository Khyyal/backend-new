<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Models\ServiceTypeTerm;

/**
 * @property ServiceTypeTerm $resource
 */
class ServiceTypeTermResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type,
            'terms' => $this->resource->exists ? $this->resource->getTranslations('terms') : null,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
