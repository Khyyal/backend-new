<?php

namespace Modules\Analytics\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The filters of an analytics request: the inclusive date range, the range
 * it is compared with (null when `compare=none`), the series bucket size and
 * the optional center / city slicers.
 */
final readonly class AnalyticsPeriod
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?CarbonImmutable $previousFrom,
        public ?CarbonImmutable $previousTo,
        public string $granularity,
        public ?int $centerId = null,
        public ?int $cityId = null,
    ) {}

    /**
     * @param  array{from?: string|null, to?: string|null, compare?: string|null, granularity?: string|null, center_id?: int|string|null, city_id?: int|string|null}  $filters
     */
    public static function fromFilters(array $filters): self
    {
        $to = isset($filters['to']) ? CarbonImmutable::parse($filters['to']) : CarbonImmutable::yesterday();
        $from = isset($filters['from']) ? CarbonImmutable::parse($filters['from']) : $to->subDays(29);
        $days = (int) $from->diffInDays($to) + 1;

        [$previousFrom, $previousTo] = match ($filters['compare'] ?? 'prior_period') {
            'none' => [null, null],
            'prior_year' => [$from->subYear(), $to->subYear()],
            default => [$from->subDays($days), $from->subDay()],
        };

        return new self(
            from: $from->startOfDay(),
            to: $to->startOfDay(),
            previousFrom: $previousFrom?->startOfDay(),
            previousTo: $previousTo?->startOfDay(),
            granularity: $filters['granularity'] ?? match (true) {
                $days <= 31 => 'day',
                $days <= 120 => 'week',
                default => 'month',
            },
            centerId: isset($filters['center_id']) ? (int) $filters['center_id'] : null,
            cityId: isset($filters['city_id']) ? (int) $filters['city_id'] : null,
        );
    }

    public function hasComparison(): bool
    {
        return $this->previousFrom !== null;
    }

    public function isSliced(): bool
    {
        return $this->centerId !== null || $this->cityId !== null;
    }

    /**
     * The start date (Y-m-d) of the series bucket a day falls in. Weeks start
     * on Sunday, matching the rest of the app.
     */
    public function bucketOf(CarbonImmutable $date): string
    {
        return match ($this->granularity) {
            'week' => $date->startOfWeek(CarbonInterface::SUNDAY)->toDateString(),
            'month' => $date->startOfMonth()->toDateString(),
            default => $date->toDateString(),
        };
    }

    /**
     * Every bucket start of the range, in order, so series can be zero-filled.
     *
     * @return list<string>
     */
    public function buckets(): array
    {
        $buckets = [];

        for ($date = $this->from; $date->lessThanOrEqualTo($this->to); $date = $date->addDay()) {
            $buckets[$this->bucketOf($date)] = true;
        }

        return array_keys($buckets);
    }

    /**
     * @return array{from: string, to: string, previous: array{from: string, to: string}|null, granularity: string}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'previous' => $this->hasComparison()
                ? ['from' => $this->previousFrom->toDateString(), 'to' => $this->previousTo->toDateString()]
                : null,
            'granularity' => $this->granularity,
        ];
    }
}
