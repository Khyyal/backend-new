<?php

namespace Modules\Services\Services;

use Illuminate\Support\Collection;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Service;

class ServiceListService
{
    /**
     * @return Collection<int, array{service_type: ServiceType, services: Collection<int, Service>}> one entry per ServiceType case
     */
    public function groupedByType(Center $center): Collection
    {
        $services = Service::query()
            ->where('center_id', $center->getKey())
            ->orderBy('id')
            ->get(['id', 'name', 'type'])
            ->groupBy(fn (Service $service): string => $service->type->value);

        return collect(ServiceType::cases())
            ->map(fn (ServiceType $type): array => [
                'service_type' => $type,
                'services' => $services->get($type->value, collect()),
            ]);
    }
}
