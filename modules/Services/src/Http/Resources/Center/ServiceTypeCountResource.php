<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Enums\ServiceType;

/**
 * @property array{service_type: ServiceType, count: int} $resource
 */
class ServiceTypeCountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'service_type' => $this->resource['service_type'],
            'count' => $this->resource['count'],
        ];
    }
}
