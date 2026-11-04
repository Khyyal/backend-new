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
            'period' => $this->resource['period'],
            'kpis' => [
                'purchases_count' => $this->resource['kpis']['purchases_count'],
                'total_revenue' => $this->resource['kpis']['total_revenue'],
                'new_clients' => $this->resource['kpis']['new_clients'],
                'repeat_client_rate' => $this->resource['kpis']['repeat_client_rate'],
            ],
            'top_services' => $this->resource['top_services'],
            'top_trainers' => $this->resource['top_trainers'],
        ];
    }
}
