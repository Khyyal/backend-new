<?php

namespace Modules\Services\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Service;

/**
 * A service with the details of its serviceable, shaped by the type's own
 * resource. Upcoming availability is handed to the resources of the types
 * that support it.
 *
 * @property Service $resource
 */
class ServiceDetailsResource extends JsonResource
{
    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $availability = null;

    /**
     * @param  array<int, array<string, mixed>>|null  $availability
     */
    public function withAvailability(?array $availability): static
    {
        $this->availability = $availability;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $serviceable = $this->resource->serviceable;

        return [
            'type' => $this->resource->type,
            'details' => match ($this->resource->type) {
                ServiceType::RecreationRiding => RecreationRidingResource::make($serviceable)->withAvailability($this->availability),
                ServiceType::Visit => VisitResource::make($serviceable),
                ServiceType::Event => EventResource::make($serviceable)->withAvailability($this->availability),
                ServiceType::Resort => ResortResource::make($serviceable),
                ServiceType::HorseCare => HorseCareResource::make($serviceable),
                ServiceType::HorseService => HorseServiceResource::make($serviceable),
                ServiceType::Training => TrainingServiceResource::make($serviceable)->withAvailability($this->availability),
            },
        ];
    }
}
