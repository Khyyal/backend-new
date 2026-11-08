<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Analytics\Tests\Support\AnalyticsFixtures;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Settlements\Database\Factories\CenterLedgerEntryFactory;
use Modules\Settlements\Enums\CenterLedgerEntryType;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Enums\SystemPermission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-21 08:00:00');
});

function analyticsViewer(): object
{
    return SystemUserFactory::new()->withPermissions([SystemPermission::ViewAnalytics])->create();
}

test('guests are unauthenticated', function (): void {
    $this->getJson('/api/v1/admin/analytics/overview')->assertUnauthorized();
});

test('system users without analytics.view are forbidden', function (): void {
    $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::ViewAnyPurchases])->create(), 'system_user')
        ->getJson('/api/v1/admin/analytics/overview')
        ->assertForbidden();
});

test('inactive system users are forbidden even with the permission', function (): void {
    $user = SystemUserFactory::new()->inactive()->withPermissions([SystemPermission::ViewAnalytics])->create();

    $this->actingAs($user, 'system_user')
        ->getJson('/api/v1/admin/analytics/overview')
        ->assertForbidden();
});

test('ranges must end before today and cover at most 366 days', function (array $query, string $field): void {
    $this->actingAs(analyticsViewer(), 'system_user')
        ->getJson('/api/v1/admin/analytics/overview?'.http_build_query($query))
        ->assertJsonValidationErrors($field);
})->with([
    'ends today' => [['to' => '2026-10-21'], 'to'],
    'from after to' => [['from' => '2026-10-10', 'to' => '2026-10-01'], 'from'],
    'longer than 366 days' => [['from' => '2025-01-01', 'to' => '2026-10-20'], 'from'],
    'unknown comparison' => [['compare' => 'last_week'], 'compare'],
]);

test('the range-too-long error names the limit', function (): void {
    $this->actingAs(analyticsViewer(), 'system_user')
        ->getJson('/api/v1/admin/analytics/overview?from=2025-01-01&to=2026-10-20')
        ->assertJsonValidationErrors(['from' => 'The date range may cover at most 366 days.']);
});

test('the overview compares the range with the one before it', function (): void {
    $center = CenterFactory::new()->create();
    AnalyticsFixtures::purchase($center, attributes: ['total_amount' => 30000, 'created_at' => '2026-10-20 10:00:00']);
    AnalyticsFixtures::purchase($center, attributes: ['total_amount' => 10000, 'created_at' => '2026-10-19 10:00:00']);
    AnalyticsFixtures::purchase($center, attributes: ['total_amount' => 20000, 'created_at' => '2026-10-17 10:00:00']);
    AnalyticsFixtures::purchase(null, attributes: ['total_amount' => 5000, 'created_at' => '2026-10-20 11:00:00']);
    $purchase = AnalyticsFixtures::purchase($center, attributes: ['total_amount' => 0, 'created_at' => '2026-10-01 10:00:00']);
    CenterLedgerEntryFactory::new()->create([
        'center_id' => $center->id, 'purchase_id' => $purchase->id, 'type' => CenterLedgerEntryType::PlatformFee, 'amount' => -4000, 'created_at' => '2026-10-20 10:00:00',
    ]);
    $this->artisan('analytics:build', ['--from' => '2026-10-01', '--to' => '2026-10-20'])->assertSuccessful();

    $response = $this->actingAs(analyticsViewer(), 'system_user')
        ->getJson('/api/v1/admin/analytics/overview?from=2026-10-19&to=2026-10-20')
        ->assertOk()
        ->assertJsonPath('data.period.previous', ['from' => '2026-10-17', 'to' => '2026-10-18'])
        ->assertJsonPath('data.period.granularity', 'day')
        ->assertJsonPath('meta.data_as_of.date', '2026-10-20');

    expect($response->json('data.kpis.gmv'))->toBe(['value' => 40000, 'previous' => 20000, 'growth' => 100])
        ->and($response->json('data.kpis.purchases_count.value'))->toBe(2)
        ->and($response->json('data.kpis.average_order_value.value'))->toBe(20000)
        ->and($response->json('data.kpis.take_rate.value'))->toBe(10)
        ->and($response->json('data.kpis.platform_net_revenue'))->toBe(['value' => 9000, 'previous' => 0, 'growth' => null])
        ->and($response->json('data.series'))->toHaveCount(2)
        ->and($response->json('data.series.1.gmv'))->toBe(30000);
});

test('compare=none gives no previous values', function (): void {
    $this->actingAs(analyticsViewer(), 'system_user')
        ->getJson('/api/v1/admin/analytics/revenue?compare=none')
        ->assertOk()
        ->assertJsonPath('data.period.previous', null)
        ->assertJsonPath('data.kpis.gmv', ['value' => 0, 'previous' => null, 'growth' => null]);
});

test('slicing by center keeps only that center and drops platform sales', function (): void {
    $center = CenterFactory::new()->create();
    $other = CenterFactory::new()->create();
    AnalyticsFixtures::purchase($center, attributes: ['total_amount' => 30000, 'created_at' => '2026-10-20 10:00:00']);
    AnalyticsFixtures::purchase($other, attributes: ['total_amount' => 70000, 'created_at' => '2026-10-20 10:00:00']);
    AnalyticsFixtures::purchase(null, attributes: ['total_amount' => 5000, 'created_at' => '2026-10-20 10:00:00']);
    $this->artisan('analytics:build', ['--from' => '2026-10-20'])->assertSuccessful();

    $this->actingAs(analyticsViewer(), 'system_user')
        ->getJson("/api/v1/admin/analytics/revenue?from=2026-10-20&to=2026-10-20&center_id={$center->id}")
        ->assertOk()
        ->assertJsonPath('data.kpis.gmv.value', 30000)
        ->assertJsonPath('data.kpis.platform_sales.value', 0);
});

test('payments report the success rate and the mix by gateway', function (): void {
    $center = CenterFactory::new()->create();
    $purchase = AnalyticsFixtures::purchase($center, attributes: ['created_at' => '2026-10-20 10:00:00']);
    PaymentFactory::new()->count(3)->sequence(
        ['status' => PaymentStatus::Succeeded, 'payment_company' => 'moyasar', 'amount' => 8000],
        ['status' => PaymentStatus::Failed, 'payment_company' => 'moyasar'],
        ['status' => PaymentStatus::Succeeded, 'payment_company' => 'tamara', 'amount' => 2000],
    )->create(['purchase_id' => $purchase->id, 'method' => PaymentMethod::Online, 'created_at' => '2026-10-20 10:01:00']);
    $this->artisan('analytics:build', ['--from' => '2026-10-20'])->assertSuccessful();

    $this->actingAs(analyticsViewer(), 'system_user')
        ->getJson('/api/v1/admin/analytics/payments?from=2026-10-20&to=2026-10-20')
        ->assertOk()
        ->assertJsonPath('data.kpis.payment_success_rate.value', 66.7)
        ->assertJsonPath('data.by_company', [
            ['key' => 'moyasar', 'attempts' => 2, 'succeeded' => 1, 'success_rate' => 50, 'amount' => 8000, 'share' => 80],
            ['key' => 'tamara', 'attempts' => 1, 'succeeded' => 1, 'success_rate' => 100, 'amount' => 2000, 'share' => 20],
        ]);
});

test('the centers leaderboard ranks by GMV with growth and flags centers whose sales dropped', function (): void {
    $leader = CenterFactory::new()->create(['name' => 'Leader']);
    $slipping = CenterFactory::new()->create(['name' => 'Slipping']);
    AnalyticsFixtures::purchase($leader, attributes: ['total_amount' => 50000, 'created_at' => '2026-10-20 10:00:00']);
    AnalyticsFixtures::purchase($slipping, attributes: ['total_amount' => 10000, 'created_at' => '2026-10-20 10:00:00']);
    AnalyticsFixtures::purchase($slipping, attributes: ['total_amount' => 40000, 'created_at' => '2026-10-19 10:00:00']);
    $this->artisan('analytics:build', ['--from' => '2026-10-19'])->assertSuccessful();

    $response = $this->actingAs(analyticsViewer(), 'system_user')
        ->getJson('/api/v1/admin/analytics/centers?from=2026-10-20&to=2026-10-20')
        ->assertOk()
        ->assertJsonPath('data.states.active', 2);

    expect(collect($response->json('data.leaderboard'))->pluck('name')->all())->toBe(['Leader', 'Slipping'])
        ->and($response->json('data.leaderboard.1.gmv'))->toBe(['value' => 10000, 'previous' => 40000, 'growth' => -75])
        ->and($response->json('data.at_risk'))->toBe([[
            'center_id' => $slipping->id, 'name' => 'Slipping', 'reason' => 'gmv_drop', 'gmv' => 10000, 'previous_gmv' => 40000, 'last_sale_on' => '2026-10-20',
        ]]);
});

test('every section endpoint answers with its blocks', function (string $section, array $keys): void {
    $this->artisan('analytics:build', ['--from' => '2026-10-19'])->assertSuccessful();

    $this->actingAs(analyticsViewer(), 'system_user')
        ->getJson("/api/v1/admin/analytics/{$section}")
        ->assertOk()
        ->assertJsonStructure(['data' => ['period', 'kpis', 'series', ...$keys], 'meta' => ['data_as_of']]);
})->with([
    'bookings' => ['bookings', ['by_service_type']],
    'clients' => ['clients', ['monthly', 'cohorts']],
    'promotions' => ['promotions', ['top_promotions', 'top_coupons']],
    'subscriptions' => ['subscriptions', []],
]);

test('a section series exports as CSV', function (): void {
    $center = CenterFactory::new()->create();
    AnalyticsFixtures::purchase($center, attributes: ['total_amount' => 30000, 'created_at' => '2026-10-20 10:00:00']);
    $this->artisan('analytics:build', ['--from' => '2026-10-20'])->assertSuccessful();

    $response = $this->actingAs(analyticsViewer(), 'system_user')
        ->get('/api/v1/admin/analytics/export?section=revenue&from=2026-10-20&to=2026-10-20')
        ->assertOk()
        ->assertDownload('analytics-revenue-2026-10-20-2026-10-20.csv');

    expect(explode("\n", trim($response->streamedContent())))->toBe([
        'date,gmv,purchases_count,net_fees,platform_sales,platform_net_revenue,refunds,discounts',
        '2026-10-20,30000,1,0,0,0,0,0',
    ]);
});
