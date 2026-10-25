<?php

namespace Modules\Services\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\ServiceType;

class ServiceTypeCountService
{
    /**
     * @return Collection<int, array{service_type: ServiceType, count: int}> one entry per ServiceType case
     */
    public function countsForCenter(Center $center): Collection
    {
        $counts = DB::table('services')
            ->where('center_id', $center->getKey())
            ->whereNull('deleted_at')
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        return collect(ServiceType::cases())
            ->map(fn (ServiceType $type) => [
                'service_type' => $type,
                'count' => (int) ($counts[$type->value] ?? 0),
            ]);
    }
}
