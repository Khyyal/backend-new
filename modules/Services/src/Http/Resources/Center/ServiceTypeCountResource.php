<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Services\Enums\ServiceType;

/**
 * @property array{service_type: ServiceType, count: int} $resource
 */
class ServiceTypeCountResource extends JsonResource
{
    #[ResponseField('service_type', 'string', 'Service type.', enum: ServiceType::class, example: 'training')]
    #[ResponseField('count', 'integer', 'Services of that type (0 when none).', example: 3)]
    public function toArray(Request $request): array
    {
        return [
            'service_type' => $this->resource['service_type'],
            'count' => $this->resource['count'],
        ];
    }
}
