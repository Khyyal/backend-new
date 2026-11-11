<?php

namespace App\Console\Commands\Migrator;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Enums\CenterEngagementAction;

#[Signature('migrator:engagement {--dry-run : Run inside a transaction and roll it back}')]
#[Description('Fold legacy action logs (center, service, map and WhatsApp clicks) into the daily center engagement counters')]
class MigrateEngagementCommand extends LegacyMigrator
{
    /**
     * Holds the last migrated `action_logs.id` (legacy_id 0 → new_id), so a
     * re-run only counts newer logs: the daily counters cannot tell which
     * events they already include.
     */
    public const ENTITY_CURSOR = 'engagement:cursor';

    private const BARN = 'App\Models\Barn';

    private const SERVICE = 'App\Models\Service';

    private const EVENT = 'App\Models\Event';

    private const MEMBERSHIP = 'App\Models\Membership';

    private const TABLE = 'analytics_daily_center_engagement';

    /** @var array<string, array{date: string, center_id: int, service_id: int, counts: array<string, int>}> */
    private array $rows = [];

    protected function migrate(): void
    {
        $this->rows = [];
        $cursor = $this->mappedId(self::ENTITY_CURSOR, 0) ?? 0;
        $last = (int) $this->legacy()->table('action_logs')->max('id');

        if ($last <= $cursor) {
            $this->tally('new action logs', 0);

            return;
        }

        $this->collectCenterClicks($cursor, $last);
        $this->collectServiceClicks($cursor, $last);
        $this->tally('action logs ignored (not center engagement)', $this->ignoredCount($cursor, $last));

        $this->writeRows();
        $this->mapId(self::ENTITY_CURSOR, 0, $last);
    }

    /**
     * Barn views, map clicks and WhatsApp clicks sit on the center's row.
     */
    private function collectCenterClicks(int $cursor, int $last): void
    {
        $actions = [
            'view_details' => CenterEngagementAction::CenterClick,
            'click_location' => CenterEngagementAction::MapClick,
            'click_whatsapp' => CenterEngagementAction::WhatsappClick,
        ];
        $centerIds = DB::table('centers')->pluck('id')->flip();

        foreach ($this->dailyCounts(self::BARN, array_keys($actions), $cursor, $last) as $group) {
            if (! $centerIds->has($group->model_id)) {
                $this->tally('events dropped (center not migrated)', (int) $group->total);

                continue;
            }

            $this->add($group->day, (int) $group->model_id, 0, $actions[$group->action], (int) $group->total);
        }
    }

    /**
     * Service, event and membership detail views sit on the migrated
     * service's row, under that service's center.
     */
    private function collectServiceClicks(int $cursor, int $last): void
    {
        $services = DB::table('services')->pluck('center_id', 'id');
        $maps = [
            self::SERVICE => null,
            self::EVENT => $this->idMap(MigrateServicesCommand::ENTITY_EVENT),
            self::MEMBERSHIP => $this->idMap(MigrateServicesCommand::ENTITY_MEMBERSHIP),
        ];

        foreach ($maps as $type => $map) {
            foreach ($this->dailyCounts($type, ['view_details'], $cursor, $last) as $group) {
                $serviceId = $map === null ? (int) $group->model_id : (int) ($map[$group->model_id] ?? 0);
                $centerId = $services->get($serviceId);

                if ($centerId === null) {
                    $this->tally('events dropped (service not migrated)', (int) $group->total);

                    continue;
                }

                $this->add($group->day, (int) $centerId, $serviceId, CenterEngagementAction::ServiceClick, (int) $group->total);
            }
        }
    }

    /**
     * Legacy logs of one model type counted per day, model and action.
     *
     * @param  list<string>  $actions
     * @return iterable<int, object{day: string, model_id: int, action: string, total: int}>
     */
    private function dailyCounts(string $modelType, array $actions, int $cursor, int $last): iterable
    {
        return $this->legacy()->table('action_logs')
            ->where('id', '>', $cursor)
            ->where('id', '<=', $last)
            ->where('model_type', $modelType)
            ->whereIn('action', $actions)
            ->whereNotNull('model_id')
            ->whereNotNull('created_at')
            ->selectRaw('DATE(created_at) as day, model_id, action, COUNT(*) as total')
            ->groupByRaw('DATE(created_at), model_id, action')
            ->cursor();
    }

    private function add(string $day, int $centerId, int $serviceId, CenterEngagementAction $action, int $count): void
    {
        $key = "{$day}|{$centerId}|{$serviceId}";

        $this->rows[$key] ??= [
            'date' => $day,
            'center_id' => $centerId,
            'service_id' => $serviceId,
            'counts' => array_fill_keys(CenterEngagementAction::columns(), 0),
        ];
        $this->rows[$key]['counts'][$action->column()] += $count;

        $this->tally(str_replace('_', ' ', $action->column()), $count);
    }

    /**
     * Adds the collected counts on top of whatever each day already holds,
     * so clicks recorded live by the new app are kept.
     */
    private function writeRows(): void
    {
        foreach ($this->rows as $row) {
            $match = ['date' => $row['date'], 'center_id' => $row['center_id'], 'service_id' => $row['service_id']];

            DB::table(self::TABLE)->insertOrIgnore($match + array_fill_keys(CenterEngagementAction::columns(), 0));

            DB::table(self::TABLE)->where($match)->update(
                collect(array_filter($row['counts']))->map(fn (int $count, string $column) => DB::raw("{$column} + {$count}"))->all(),
            );

            $this->tally('engagement rows written');
        }
    }

    /**
     * Logs in the range that are not center engagement: searches, ads,
     * horse and stabling views, reservation updates.
     */
    private function ignoredCount(int $cursor, int $last): int
    {
        return $this->legacy()->table('action_logs')
            ->where('id', '>', $cursor)
            ->where('id', '<=', $last)
            ->where(fn ($query) => $query
                ->whereNull('model_type')
                ->orWhereNotIn('model_type', [self::BARN, self::SERVICE, self::EVENT, self::MEMBERSHIP])
                ->orWhere(fn ($query) => $query->where('model_type', self::BARN)->whereNotIn('action', ['view_details', 'click_location', 'click_whatsapp']))
                ->orWhere(fn ($query) => $query->whereIn('model_type', [self::SERVICE, self::EVENT, self::MEMBERSHIP])->where('action', '!=', 'view_details')))
            ->count();
    }
}
