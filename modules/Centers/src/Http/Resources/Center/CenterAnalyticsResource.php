<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
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
    #[ResponseField('period', 'object', 'Ranges compared.')]
    #[ResponseField('period.from', 'string', 'Range start, `Y-m-d`.', example: '2026-05-01')]
    #[ResponseField('period.to', 'string', 'Range end, `Y-m-d`.', example: '2026-05-30')]
    #[ResponseField('period.previous_from', 'string', 'Previous span start, `Y-m-d`.', example: '2026-04-01')]
    #[ResponseField('period.previous_to', 'string', 'Previous span end, `Y-m-d`.', example: '2026-04-30')]
    #[ResponseField('kpis.purchases_count', 'object', 'Confirmed and completed purchases.')]
    #[ResponseField('kpis.purchases_count.value', 'integer', 'Value over the range.', example: 42)]
    #[ResponseField('kpis.purchases_count.previous', 'integer', 'Value over the same-length span right before the range.', example: 35)]
    #[ResponseField('kpis.purchases_count.growth', 'number', 'Change in percent; null when the previous value is 0.', nullable: true, example: 20.0)]
    #[ResponseField('kpis.total_revenue', 'object', 'Revenue in halalas of those purchases.')]
    #[ResponseField('kpis.total_revenue.value', 'integer', 'Value over the range.', example: 1260000)]
    #[ResponseField('kpis.total_revenue.previous', 'integer', 'Value over the same-length span right before the range.', example: 1050000)]
    #[ResponseField('kpis.total_revenue.growth', 'number', 'Change in percent; null when the previous value is 0.', nullable: true, example: 20.0)]
    #[ResponseField('kpis.new_clients', 'object', 'Clients whose first purchase at the center is in the range.')]
    #[ResponseField('kpis.new_clients.value', 'integer', 'Value over the range.', example: 18)]
    #[ResponseField('kpis.new_clients.previous', 'integer', 'Value over the same-length span right before the range.', example: 15)]
    #[ResponseField('kpis.new_clients.growth', 'number', 'Change in percent; null when the previous value is 0.', nullable: true, example: 20.0)]
    #[ResponseField('kpis.repeat_client_rate', 'object', 'Percent (0-100) of the range\'s buyers with two or more purchases at the center up to the range end.')]
    #[ResponseField('kpis.repeat_client_rate.value', 'number', 'Value over the range.', example: 33.3)]
    #[ResponseField('kpis.repeat_client_rate.previous', 'number', 'Value over the same-length span right before the range.', example: 28.6)]
    #[ResponseField('kpis.repeat_client_rate.growth', 'number', 'Change in percent; null when the previous value is 0.', nullable: true, example: 16.43)]
    #[ResponseField('top_services', 'object[]', 'Services ranked by purchases (size `limit`).')]
    #[ResponseField('top_services[].service_id', 'integer', 'Service ID.', example: 31)]
    #[ResponseField('top_services[].name', 'string', 'Service name in the request locale.', nullable: true, example: 'Beginner ride')]
    #[ResponseField('top_services[].type', 'string', 'Service type.', nullable: true, example: 'recreation_riding')]
    #[ResponseField('top_services[].purchases_count', 'integer', 'Purchases in the range.', example: 20)]
    #[ResponseField('top_trainers', 'object[]', 'Trainers ranked by non-cancelled reservations made in the range (size `limit`).')]
    #[ResponseField('top_trainers[].trainer_id', 'integer', 'Trainer ID.', example: 6)]
    #[ResponseField('top_trainers[].name', 'string', 'Trainer name.', nullable: true, example: 'Fahad')]
    #[ResponseField('top_trainers[].reservations_count', 'integer', 'Reservations in the range.', example: 25)]
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
