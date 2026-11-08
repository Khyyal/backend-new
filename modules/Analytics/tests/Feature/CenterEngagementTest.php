<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Enums\SystemPermission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');
});

function engagementRow(int $centerId, int $serviceId = 0, string $date = '2026-10-20'): ?object
{
    return DB::table('analytics_daily_center_engagement')
        ->where(['date' => $date, 'center_id' => $centerId, 'service_id' => $serviceId])
        ->first();
}

test('engagement events are aggregated per day, center and service', function (): void {
    $center = CenterFactory::new()->create();
    $service = ServiceFactory::new()->create(['center_id' => $center->id]);

    $events = [
        ['action' => 'center_click', 'center_id' => $center->id],
        ['action' => 'center_click', 'center_id' => $center->id],
        ['action' => 'map_click', 'center_id' => $center->id],
        ['action' => 'whatsapp_click', 'center_id' => $center->id],
        ['action' => 'service_click', 'center_id' => $center->id, 'service_id' => $service->id],
    ];

    $this->postJson('/api/v1/clients/engagement/events', ['events' => $events])
        ->assertAccepted()
        ->assertJsonPath('data.accepted', 5);

    $this->postJson('/api/v1/clients/engagement/events', ['events' => [$events[0]]])
        ->assertJsonPath('data.accepted', 1);

    expect(engagementRow($center->id))
        ->center_clicks->toBe(3)
        ->map_clicks->toBe(1)
        ->whatsapp_clicks->toBe(1)
        ->service_clicks->toBe(0)
        ->and(engagementRow($center->id, $service->id))
        ->service_clicks->toBe(1)
        ->center_clicks->toBe(0);
});

test('events for unknown centers or another center\'s service are dropped', function (): void {
    $center = CenterFactory::new()->create();
    $foreignService = ServiceFactory::new()->create();

    $this->postJson('/api/v1/clients/engagement/events', ['events' => [
        ['action' => 'center_click', 'center_id' => 999999],
        ['action' => 'service_click', 'center_id' => $center->id, 'service_id' => $foreignService->id],
        ['action' => 'map_click', 'center_id' => $center->id],
    ]])->assertJsonPath('data.accepted', 1);

    expect(DB::table('analytics_daily_center_engagement')->count())->toBe(1)
        ->and(engagementRow($center->id)->map_clicks)->toBe(1);
});

test('invalid batches are rejected', function (array $payload, string $error): void {
    $this->postJson('/api/v1/clients/engagement/events', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);
})->with([
    'service click without service' => [['events' => [['action' => 'service_click', 'center_id' => 1]]], 'events.0.service_id'],
    'unknown action' => [['events' => [['action' => 'share', 'center_id' => 1]]], 'events.0.action'],
    'too many events' => [['events' => array_fill(0, 51, ['action' => 'center_click', 'center_id' => 1])], 'events'],
]);

test('reporting is throttled', function (): void {
    $center = CenterFactory::new()->create();
    $payload = ['events' => [['action' => 'center_click', 'center_id' => $center->id]]];

    for ($i = 0; $i < 60; $i++) {
        $this->postJson('/api/v1/clients/engagement/events', $payload)->assertAccepted();
    }

    $this->postJson('/api/v1/clients/engagement/events', $payload)->assertTooManyRequests();
});

test('admins without analytics.view cannot see engagement', function (): void {
    $this->getJson('/api/v1/admin/analytics/engagement')->assertUnauthorized();

    $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::ViewAnyPurchases])->create(), 'system_user')
        ->getJson('/api/v1/admin/analytics/engagement')
        ->assertForbidden();
});

test('admins see engagement kpis, rankings and city slices', function (): void {
    $leader = CenterFactory::new()->create(['name' => 'Leader']);
    $other = CenterFactory::new()->create(['name' => 'Other']);
    $service = ServiceFactory::new()->create(['center_id' => $leader->id]);

    $this->travelTo('2026-10-12 08:00:00');
    $this->postJson('/api/v1/clients/engagement/events', ['events' => [
        ['action' => 'center_click', 'center_id' => $leader->id],
    ]]);

    $this->travelTo('2026-10-19 08:00:00');
    $this->postJson('/api/v1/clients/engagement/events', ['events' => [
        ['action' => 'center_click', 'center_id' => $leader->id],
        ['action' => 'center_click', 'center_id' => $leader->id],
        ['action' => 'whatsapp_click', 'center_id' => $leader->id],
        ['action' => 'service_click', 'center_id' => $leader->id, 'service_id' => $service->id],
        ['action' => 'map_click', 'center_id' => $other->id],
    ]]);

    $this->travelTo('2026-10-21 08:00:00');
    $viewer = SystemUserFactory::new()->withPermissions([SystemPermission::ViewAnalytics])->create();

    $this->actingAs($viewer, 'system_user')
        ->getJson('/api/v1/admin/analytics/engagement?from=2026-10-16&to=2026-10-20')
        ->assertOk()
        ->assertJsonPath('data.kpis.center_clicks', ['value' => 2, 'previous' => 1, 'growth' => 100])
        ->assertJsonPath('data.kpis.service_clicks.value', 1)
        ->assertJsonPath('data.kpis.map_clicks.value', 1)
        ->assertJsonPath('data.kpis.whatsapp_clicks.value', 1)
        ->assertJsonCount(5, 'data.series')
        ->assertJsonPath('data.series.3', ['date' => '2026-10-19', 'center_clicks' => 2, 'service_clicks' => 1, 'map_clicks' => 1, 'whatsapp_clicks' => 1])
        ->assertJsonPath('data.top_centers.0.name', 'Leader')
        ->assertJsonPath('data.top_centers.0.total_clicks', 4)
        ->assertJsonPath('data.top_centers.1.center_id', $other->id)
        ->assertJsonPath('data.top_services.0.service_id', $service->id)
        ->assertJsonPath('data.top_services.0.service_clicks', 1);

    $this->actingAs($viewer, 'system_user')
        ->getJson("/api/v1/admin/analytics/engagement?from=2026-10-16&to=2026-10-20&city_id={$other->city_id}")
        ->assertOk()
        ->assertJsonPath('data.kpis.map_clicks.value', 1)
        ->assertJsonPath('data.kpis.center_clicks.value', 0)
        ->assertJsonCount(1, 'data.top_centers');
});

test('engagement series exports as CSV', function (): void {
    $center = CenterFactory::new()->create();
    $this->postJson('/api/v1/clients/engagement/events', ['events' => [
        ['action' => 'whatsapp_click', 'center_id' => $center->id],
    ]]);

    $this->travelTo('2026-10-21 08:00:00');

    $response = $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::ViewAnalytics])->create(), 'system_user')
        ->get('/api/v1/admin/analytics/export?section=engagement&from=2026-10-20&to=2026-10-20')
        ->assertOk()
        ->assertDownload('analytics-engagement-2026-10-20-2026-10-20.csv');

    expect(explode("\n", trim($response->streamedContent())))->toBe([
        'date,center_clicks,service_clicks,map_clicks,whatsapp_clicks',
        '2026-10-20,0,0,0,1',
    ]);
});
