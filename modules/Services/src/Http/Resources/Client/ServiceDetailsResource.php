<?php

namespace Modules\Services\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Service;

/**
 * A service with the details of its serviceable, shaped by the type's own resource.
 *
 * @property Service $resource
 */
class ServiceDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $serviceable = $this->resource->serviceable;

        return [
            'type' => $this->resource->type,
            'details' => match ($this->resource->type) {
                ServiceType::RecreationRiding => RecreationRidingResource::make($serviceable),
                ServiceType::Visit => VisitResource::make($serviceable),
                ServiceType::Event => EventResource::make($serviceable),
                ServiceType::Resort => ResortResource::make($serviceable),
                ServiceType::HorseCare => HorseCareResource::make($serviceable),
                ServiceType::HorseService => HorseServiceResource::make($serviceable),
                ServiceType::Training => TrainingServiceResource::make($serviceable),
            },
        ];
    }
}
