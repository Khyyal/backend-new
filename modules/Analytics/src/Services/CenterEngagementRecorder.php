<?php

namespace Modules\Analytics\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Enums\CenterEngagementAction;

/**
 * Aggregates client engagement batches into one row per (day, center, service). No per-event
 * table and no identity are stored.
 *
 * Counters are bumped with single `UPDATE ... SET n = n + ?` statements after an insert-or-ignore
 * of the row, which is atomic on both MySQL and SQLite and safe under concurrent batches.
 */
class CenterEngagementRecorder
{
    /**
     * @param  list<array{action: string, center_id: int, service_id?: int|null}>  $events
     * @return int Number of events counted (events for unknown centers or services are dropped).
     */
    public function record(array $events, ?CarbonInterface $at = null): int
    {
        $date = ($at ?? now())->toDateString();
        $rows = [];

        foreach ($events as $event) {
            $action = CenterEngagementAction::from($event['action']);
            $centerId = (int) $event['center_id'];
            $serviceId = $action === CenterEngagementAction::ServiceClick ? (int) ($event['service_id'] ?? 0) : 0;

            if ($action === CenterEngagementAction::ServiceClick && $serviceId === 0) {
                continue;
            }

            $key = $centerId.':'.$serviceId;
            $rows[$key] ??= ['center_id' => $centerId, 'service_id' => $serviceId, 'counts' => array_fill_keys(CenterEngagementAction::columns(), 0)];
            $rows[$key]['counts'][$action->column()]++;
        }

        $rows = $this->onlyExisting($rows);
        $recorded = 0;

        DB::transaction(function () use ($rows, $date, &$recorded): void {
            foreach ($rows as $row) {
                $match = ['date' => $date, 'center_id' => $row['center_id'], 'service_id' => $row['service_id']];
                $increments = array_filter($row['counts']);

                DB::table('analytics_daily_center_engagement')->insertOrIgnore($match + array_fill_keys(CenterEngagementAction::columns(), 0));

                DB::table('analytics_daily_center_engagement')->where($match)->update(
                    collect($increments)->map(fn (int $count, string $column) => DB::raw($column.' + '.$count))->all(),
                );

                $recorded += array_sum($increments);
            }
        });

        return $recorded;
    }

    /**
     * Keep rows whose center exists and, for service rows, whose service belongs to that center.
     *
     * @param  array<string, array{center_id: int, service_id: int, counts: array<string, int>}>  $rows
     * @return array<string, array{center_id: int, service_id: int, counts: array<string, int>}>
     */
    private function onlyExisting(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $centerIds = DB::table('centers')
            ->whereIn('id', array_unique(array_column($rows, 'center_id')))
            ->whereNull('deleted_at')
            ->pluck('id')
            ->flip();

        $serviceIds = array_values(array_unique(array_filter(array_column($rows, 'service_id'))));
        $services = $serviceIds === [] ? collect() : DB::table('services')
            ->whereIn('id', $serviceIds)
            ->whereNull('deleted_at')
            ->pluck('center_id', 'id');

        return array_filter($rows, fn (array $row): bool => $centerIds->has($row['center_id'])
            && ($row['service_id'] === 0 || (int) $services->get($row['service_id']) === $row['center_id']));
    }
}
