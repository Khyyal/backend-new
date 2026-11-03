<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Service;

/**
 * @property array{service_type: ServiceType, services: Collection<int, Service>} $resource
 */
class ServiceTypeGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'service_type' => $this->resource['service_type'],
            'services' => $this->resource['services']
                ->map(fn (Service $service): array => [
                    'id' => $service->id,
                    'name' => $service->name,
                ])
                ->values(),
        ];
    }
}
