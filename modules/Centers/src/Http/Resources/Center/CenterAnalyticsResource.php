<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Centers\Services\CenterAnalytics;

/**
 * @property array{
 *     period: array{from: string, to: string, previous_from: string, previous_to: string},
 *     kpis: array<string, array{value: int|float, previous: int|float, growth: ?float}>,
 *     top_services: list<array{service_id: int, name: ?string, type: ?string, purchases_count: int}>,
 *     top_trainers: list<array{trainer_id: int, name: ?string, reservations_count: int}>,
 * } $resource See {@see CenterAnalytics::overview()}.
 */
class CenterAnalyticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var array{from: string, to: string, previous_from: string, previous_to: string} */
            'period' => $this->resource['period'],
            'kpis' => [
                /** @var array{value: int, previous: int, growth: float|null} */
                'purchases_count' => $this->resource['kpis']['purchases_count'],
                /** @var array{value: int, previous: int, growth: float|null} Amounts in halalas. */
                'total_revenue' => $this->resource['kpis']['total_revenue'],
                /** @var array{value: int, previous: int, growth: float|null} */
                'new_clients' => $this->resource['kpis']['new_clients'],
                /** @var array{value: float, previous: float, growth: float|null} Percentages (0-100). */
                'repeat_client_rate' => $this->resource['kpis']['repeat_client_rate'],
            ],
            /** @var list<array{service_id: int, name: string|null, type: string|null, purchases_count: int}> */
            'top_services' => $this->resource['top_services'],
            /** @var list<array{trainer_id: int, name: string|null, reservations_count: int}> */
            'top_trainers' => $this->resource['top_trainers'],
        ];
    }
}
