<?php

namespace Modules\Homepage\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Homepage\Enums\ContentEntityType;
use Modules\Homepage\Enums\ContentEventType;

/**
 * Aggregates impression/click batches into one row per (day, entity). No per-event table and no
 * identity are stored, so this is cheap and carries no personal data.
 *
 * Counters are bumped with single `UPDATE ... SET n = n + ?` statements after an insert-or-ignore of the
 * row, which is atomic on both MySQL and SQLite and safe under concurrent batches.
 */
class ContentEventRecorder
{
    /**
     * @param  list<array{type: string, entity_type: string, entity_id: int}>  $events
     * @return int Number of events counted (events pointing at unknown entities are dropped).
     */
    public function record(array $events, ?CarbonInterface $at = null): int
    {
        $date = ($at ?? now())->toDateString();
        $counts = [];

        foreach ($events as $event) {
            $key = $event['entity_type'].':'.$event['entity_id'];
            $counts[$key] ??= ['entity_type' => $event['entity_type'], 'entity_id' => (int) $event['entity_id'], 'impressions' => 0, 'clicks' => 0];
            $counts[$key][$event['type'] === ContentEventType::Click->value ? 'clicks' : 'impressions']++;
        }

        $counts = $this->onlyExisting($counts);
        $recorded = 0;

        DB::transaction(function () use ($counts, $date, &$recorded): void {
            foreach ($counts as $row) {
                $match = ['date' => $date, 'entity_type' => $row['entity_type'], 'entity_id' => $row['entity_id']];

                DB::table('content_daily_stats')->insertOrIgnore($match + ['impressions' => 0, 'clicks' => 0]);

                DB::table('content_daily_stats')->where($match)->update([
                    'impressions' => DB::raw('impressions + '.(int) $row['impressions']),
                    'clicks' => DB::raw('clicks + '.(int) $row['clicks']),
                ]);

                $recorded += $row['impressions'] + $row['clicks'];
            }
        });

        return $recorded;
    }

    /**
     * @param  array<string, array{entity_type: string, entity_id: int, impressions: int, clicks: int}>  $counts
     * @return array<string, array{entity_type: string, entity_id: int, impressions: int, clicks: int}>
     */
    private function onlyExisting(array $counts): array
    {
        $existing = [];

        foreach (collect($counts)->groupBy('entity_type') as $type => $rows) {
            $ids = DB::table(ContentEntityType::from($type)->table())->whereIn('id', $rows->pluck('entity_id')->all())->pluck('id');

            foreach ($ids as $id) {
                $existing[$type.':'.$id] = true;
            }
        }

        return array_intersect_key($counts, $existing);
    }
}
