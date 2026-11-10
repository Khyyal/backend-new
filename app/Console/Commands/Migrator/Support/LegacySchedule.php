<?php

namespace App\Console\Commands\Migrator\Support;

/**
 * Converts the legacy schedule shape — a JSON list of weekday names plus a
 * JSON list of one-hour slot starts shared by every listed day — into the new
 * one: Carbon weekday numbers (0 = Sunday) and [start, end] time windows,
 * where an end of day is `24:00:00`.
 */
final class LegacySchedule
{
    public const END_OF_DAY = '24:00:00';

    private const WEEKDAYS = [
        'sunday' => 0,
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
    ];

    /**
     * @return array<int, int> Distinct weekday numbers, sorted.
     */
    public static function days(mixed $json): array
    {
        $days = collect(self::decode($json))
            ->map(fn (mixed $day): ?int => is_string($day) ? (self::WEEKDAYS[strtolower(trim($day))] ?? null) : null)
            ->filter(fn (?int $day): bool => $day !== null)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $days;
    }

    /**
     * Weekday number for a single legacy day name, or null when unknown.
     */
    public static function day(string $name): ?int
    {
        return self::WEEKDAYS[strtolower(trim($name))] ?? null;
    }

    /**
     * Merges one-hour slot starts ("11:00", or a bare "6") into windows.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function windows(mixed $json): array
    {
        $hours = collect(self::decode($json))
            ->map(fn (mixed $slot): ?int => self::hour($slot))
            ->filter(fn (?int $hour): bool => $hour !== null)
            ->unique()
            ->sort()
            ->values();

        $windows = [];
        $start = null;
        $previous = null;

        foreach ($hours as $hour) {
            if ($start !== null && $hour !== $previous + 1) {
                $windows[] = self::window($start, $previous + 1);
                $start = null;
            }

            $start ??= $hour;
            $previous = $hour;
        }

        if ($start !== null) {
            $windows[] = self::window($start, $previous + 1);
        }

        return $windows;
    }

    /**
     * `schedules` rows for every day × window (or one all-day row per day
     * with null times when there are no windows and $allDay is set).
     *
     * @param  array<int, int>  $days
     * @param  array<int, array{0: string, 1: string}>  $windows
     * @return array<int, array<string, mixed>>
     */
    public static function scheduleRows(string $type, int $id, array $days, array $windows, bool $allDay = false): array
    {
        $rows = [];

        foreach ($days as $day) {
            if ($allDay) {
                $rows[] = ['schedulable_type' => $type, 'schedulable_id' => $id, 'day_of_week' => $day, 'start_time' => null, 'end_time' => null];

                continue;
            }

            foreach ($windows as [$start, $end]) {
                $rows[] = ['schedulable_type' => $type, 'schedulable_id' => $id, 'day_of_week' => $day, 'start_time' => $start, 'end_time' => $end];
            }
        }

        return $rows;
    }

    /**
     * Whether any slot starts after midnight but before the first daytime
     * slot of a late schedule (e.g. 18:00–03:00), which the new model can
     * only store as a separate early-morning window on the same day.
     *
     * @param  array<int, array{0: string, 1: string}>  $windows
     */
    public static function wrapsPastMidnight(array $windows): bool
    {
        return count($windows) > 1
            && $windows[0][0] === '00:00:00'
            && end($windows)[1] === self::END_OF_DAY;
    }

    private static function hour(mixed $slot): ?int
    {
        if (is_int($slot)) {
            $hour = $slot;
        } elseif (is_string($slot) && preg_match('/^\s*(\d{1,2})(?::\d{2})?\s*$/', $slot, $matches) === 1) {
            $hour = (int) $matches[1];
        } else {
            return null;
        }

        return $hour >= 0 && $hour <= 23 ? $hour : null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function window(int $start, int $end): array
    {
        return [
            sprintf('%02d:00:00', $start),
            $end >= 24 ? self::END_OF_DAY : sprintf('%02d:00:00', $end),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function decode(mixed $json): array
    {
        if (is_array($json)) {
            return $json;
        }

        if (! is_string($json) || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }
}
