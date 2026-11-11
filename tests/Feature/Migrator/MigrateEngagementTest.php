<?php

use App\Console\Commands\Migrator\MigrateEngagementCommand;
use App\Console\Commands\Migrator\MigrateServicesCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Database\Factories\ServiceFactory;

require_once __DIR__.'/LegacySchema.php';

uses(RefreshDatabase::class);

/**
 * Inserts one legacy action log.
 */
function legacyActionLog(?string $model, ?int $modelId, string $action, string $at): void
{
    DB::connection('legacy')->table('action_logs')->insert([
        'model_type' => $model === null ? null : "App\\Models\\{$model}",
        'model_id' => $modelId,
        'action' => $action,
        'created_at' => $at,
        'updated_at' => $at,
    ]);
}

/**
 * The engagement row of a day, center and service as a plain array of counters.
 *
 * @return array<string, int>|null
 */
function migratedEngagementRow(string $date, int $centerId, int $serviceId = 0): ?array
{
    $row = DB::table('analytics_daily_center_engagement')
        ->whereDate('date', $date)
        ->where(['center_id' => $centerId, 'service_id' => $serviceId])
        ->first(['center_clicks', 'service_clicks', 'map_clicks', 'whatsapp_clicks']);

    return $row === null ? null : array_map(intval(...), (array) $row);
}

beforeEach(function (): void {
    useLegacyServiceSchema();

    CenterFactory::new()->create(['id' => 6]);
    ServiceFactory::new()->create(['id' => 40, 'center_id' => 6]);
    ServiceFactory::new()->create(['id' => 41, 'center_id' => 6]);
    ServiceFactory::new()->create(['id' => 42, 'center_id' => 6]);

    DB::table('legacy_id_maps')->insert([
        ['entity' => MigrateServicesCommand::ENTITY_EVENT, 'legacy_id' => 3, 'new_id' => 41],
        ['entity' => MigrateServicesCommand::ENTITY_MEMBERSHIP, 'legacy_id' => 3, 'new_id' => 42],
    ]);
});

test('barn views, map and whatsapp clicks are counted per day on the center row', function (): void {
    legacyActionLog('Barn', 6, 'view_details', '2026-01-10 08:00:00');
    legacyActionLog('Barn', 6, 'view_details', '2026-01-10 23:59:59');
    legacyActionLog('Barn', 6, 'click_location', '2026-01-10 09:00:00');
    legacyActionLog('Barn', 6, 'click_whatsapp', '2026-01-11 09:00:00');

    $this->artisan('migrator:engagement')->assertSuccessful();

    expect(migratedEngagementRow('2026-01-10', 6))->toBe(['center_clicks' => 2, 'service_clicks' => 0, 'map_clicks' => 1, 'whatsapp_clicks' => 0])
        ->and(migratedEngagementRow('2026-01-11', 6))->toBe(['center_clicks' => 0, 'service_clicks' => 0, 'map_clicks' => 0, 'whatsapp_clicks' => 1]);
});

test('service, event and membership views are counted on the migrated service row', function (): void {
    legacyActionLog('Service', 40, 'view_details', '2026-01-10 08:00:00');
    legacyActionLog('Service', 40, 'view_details', '2026-01-10 09:00:00');
    legacyActionLog('Event', 3, 'view_details', '2026-01-10 08:00:00');
    legacyActionLog('Membership', 3, 'view_details', '2026-01-10 08:00:00');

    $this->artisan('migrator:engagement')->assertSuccessful();

    expect(migratedEngagementRow('2026-01-10', 6, 40)['service_clicks'])->toBe(2)
        ->and(migratedEngagementRow('2026-01-10', 6, 41)['service_clicks'])->toBe(1)
        ->and(migratedEngagementRow('2026-01-10', 6, 42)['service_clicks'])->toBe(1)
        ->and(migratedEngagementRow('2026-01-10', 6))->toBeNull();
});

test('other actions, horse and stabling views and unmigrated targets are not counted', function (): void {
    legacyActionLog(null, null, 'click_top_service', '2026-01-10 08:00:00');
    legacyActionLog('HorseService', 40, 'view_details', '2026-01-10 08:00:00');
    legacyActionLog('StablingService', 40, 'view_details', '2026-01-10 08:00:00');
    legacyActionLog('Reservation', 40, 'update', '2026-01-10 08:00:00');
    legacyActionLog('Barn', 999, 'view_details', '2026-01-10 08:00:00');
    legacyActionLog('Service', 999, 'view_details', '2026-01-10 08:00:00');
    legacyActionLog('Event', 999, 'view_details', '2026-01-10 08:00:00');

    $this->artisan('migrator:engagement')->assertSuccessful();

    expect(DB::table('analytics_daily_center_engagement')->count())->toBe(0);
});

test('a re-run only adds logs newer than the last migrated one and keeps live counts', function (): void {
    DB::table('analytics_daily_center_engagement')->insert([
        'date' => '2026-01-10', 'center_id' => 6, 'service_id' => 0, 'center_clicks' => 5, 'map_clicks' => 1,
    ]);
    legacyActionLog('Barn', 6, 'view_details', '2026-01-10 08:00:00');

    $this->artisan('migrator:engagement')->assertSuccessful();
    $this->artisan('migrator:engagement')->assertSuccessful();

    expect(migratedEngagementRow('2026-01-10', 6))->toBe(['center_clicks' => 6, 'service_clicks' => 0, 'map_clicks' => 1, 'whatsapp_clicks' => 0]);

    legacyActionLog('Barn', 6, 'view_details', '2026-01-10 10:00:00');

    $this->artisan('migrator:engagement')->assertSuccessful();

    expect(migratedEngagementRow('2026-01-10', 6)['center_clicks'])->toBe(7);
});

test('a dry run writes no counters and does not move the cursor', function (): void {
    legacyActionLog('Barn', 6, 'view_details', '2026-01-10 08:00:00');

    $this->artisan('migrator:engagement', ['--dry-run' => true])->assertSuccessful();

    expect(DB::table('analytics_daily_center_engagement')->count())->toBe(0)
        ->and(DB::table('legacy_id_maps')->where('entity', MigrateEngagementCommand::ENTITY_CURSOR)->exists())->toBeFalse();
});
