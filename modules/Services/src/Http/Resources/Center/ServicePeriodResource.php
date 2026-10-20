<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Enums\PeriodGender;
use Modules\Services\Models\Service;

/**
 * @property Service $resource
 */
class ServicePeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $periods = $this->resource->periods->groupBy(fn ($period) => $period->gender->value);

        $result = [];
        foreach (PeriodGender::cases() as $gender) {
            $rows = $periods->get($gender->value);

            $result[$gender->value] = $rows === null ? null : [
                'days' => $rows->pluck('day_of_week')->unique()->sort()->values(),
                'hours' => $rows->sortBy('start_time')
                    ->map(fn ($period) => [substr($period->start_time, 0, 5), substr($period->end_time, 0, 5)])
                    ->unique(fn ($pair) => implode('-', $pair))
                    ->flatten()
                    ->values(),
            ];
        }

        return ['service_id' => $this->resource->id, ...$result];
    }
}
