<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Homepage\Database\Factories\BannerFactory;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Enums\SystemPermission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('aggregates events per day and ignores unknown entities', function (): void {
    $banner = BannerFactory::new()->create();

    $this->postJson('/api/v1/clients/home/events', ['events' => [
        ['type' => 'impression', 'entity_type' => 'banner', 'entity_id' => $banner->id],
        ['type' => 'impression', 'entity_type' => 'banner', 'entity_id' => $banner->id],
        ['type' => 'click', 'entity_type' => 'banner', 'entity_id' => $banner->id],
        ['type' => 'click', 'entity_type' => 'center', 'entity_id' => 999999],
    ]])
        ->assertAccepted()
        ->assertJsonPath('data.accepted', 3);

    $this->postJson('/api/v1/clients/home/events', ['events' => [
        ['type' => 'click', 'entity_type' => 'banner', 'entity_id' => $banner->id],
    ]])->assertAccepted();

    $row = DB::table('content_daily_stats')->sole();

    expect($row->entity_type)->toBe('banner')
        ->and((int) $row->impressions)->toBe(2)
        ->and((int) $row->clicks)->toBe(2);
});

test('rejects malformed events', function (): void {
    $this->postJson('/api/v1/clients/home/events', ['events' => [['type' => 'hover', 'entity_type' => 'banner', 'entity_id' => 1]]])
        ->assertJsonValidationErrors('events.0.type');
});

test('the dashboard reports totals, prior period and top content', function (): void {
    $center = CenterFactory::new()->create(['name' => 'Desert Riders']);
    $stat = fn (string $date, int $impressions, int $clicks) => DB::table('content_daily_stats')->insert([
        'date' => $date, 'entity_type' => 'center', 'entity_id' => $center->id, 'impressions' => $impressions, 'clicks' => $clicks,
    ]);
    $stat('2026-10-10', 100, 10);
    $stat('2026-10-11', 100, 30);
    $stat('2026-10-08', 50, 5);

    $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::ManageHomepage])->create(), 'system_user')
        ->getJson('/api/v1/admin/analytics/home-content?from_date=2026-10-10&to_date=2026-10-11')
        ->assertOk()
        ->assertJsonPath('data.period.prior', ['from' => '2026-10-08', 'to' => '2026-10-09'])
        ->assertJsonPath('data.kpis.impressions.value', 200)
        ->assertJsonPath('data.kpis.impressions.prior', 50)
        ->assertJsonPath('data.kpis.clicks.value', 40)
        ->assertJsonPath('data.kpis.click_through_rate.value', 20)
        ->assertJsonCount(2, 'data.series')
        ->assertJsonPath('data.top_content.0.label', 'Desert Riders')
        ->assertJsonPath('data.top_content.0.clicks', 40);
});

test('the dashboard requires the homepage permission', function (): void {
    $this->actingAs(SystemUserFactory::new()->create(), 'system_user')
        ->getJson('/api/v1/admin/analytics/home-content')
        ->assertForbidden();
});
