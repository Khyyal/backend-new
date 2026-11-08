<?php

namespace Modules\Analytics\Support;

/**
 * KPI value helpers shared by the analytics sections.
 */
final class Kpi
{
    /**
     * A KPI tile: the value, the comparison value and the growth between them
     * in percent (null without a comparison or when the previous value is 0).
     *
     * @return array{value: int|float, previous: int|float|null, growth: float|null}
     */
    public static function compare(int|float $value, int|float|null $previous): array
    {
        return [
            'value' => $value,
            'previous' => $previous,
            'growth' => $previous === null || $previous == 0 ? null : round(($value - $previous) / abs($previous) * 100, 1),
        ];
    }

    /**
     * Compare every metric of the current range with the previous one.
     *
     * @param  array<string, int|float>  $current
     * @param  array<string, int|float>|null  $previous
     * @return array<string, array{value: int|float, previous: int|float|null, growth: float|null}>
     */
    public static function compareAll(array $current, ?array $previous): array
    {
        return collect($current)
            ->map(fn (int|float $value, string $key): array => self::compare($value, $previous[$key] ?? null))
            ->all();
    }

    /**
     * `part / whole` as a percentage with one decimal; 0 when whole is 0.
     */
    public static function rate(int|float $part, int|float $whole): float
    {
        return $whole == 0 ? 0.0 : round($part / $whole * 100, 1);
    }

    /**
     * `total / count` rounded down to whole halalas; 0 when count is 0.
     */
    public static function average(int $total, int $count): int
    {
        return $count === 0 ? 0 : intdiv($total, $count);
    }
}
