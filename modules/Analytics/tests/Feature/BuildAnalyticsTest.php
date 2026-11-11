<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Actions\BuildClientActivity;
use Modules\Analytics\Actions\BuildDailyCenterStats;
use Modules\Analytics\Actions\BuildDailyPlatformStats;
use Modules\Analytics\Tests\Support\AnalyticsFixtures;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Settlements\Database\Factories\CenterFinancialBaselineFactory;
use Modules\Settlements\Database\Factories\CenterLedgerEntryFactory;
use Modules\Settlements\Enums\CenterLedgerEntryType;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-21 08:00:00');
});

function analyticsCenterStats(string $date, int $centerId): ?object
{
    return DB::table('analytics_daily_center_stats')->where('date', $date)->where('center_id', $centerId)->first();
}

test('only confirmed and completed purchases count toward GMV, every purchase toward all purchases', function (): void {
    $center = CenterFactory::new()->create();
    $day = '2026-10-20 10:00:00';

    AnalyticsFixtures::purchase($center, attributes: ['total_amount' => 10000, 'created_at' => $day]);
    AnalyticsFixtures::purchase($center, attributes: ['total_amount' => 5000, 'status' => PurchaseStatus::Completed, 'created_at' => $day]);
    AnalyticsFixtures::purchase($center, attributes: ['total_amount' => 7000, 'status' => PurchaseStatus::Cancelled, 'created_at' => $day]);
    AnalyticsFixtures::purchase($center, attributes: ['total_amount' => 9000, 'status' => PurchaseStatus::Pending, 'created_at' => $day]);
    AnalyticsFixtures::purchase($center, attributes: ['total_amount' => 3000, 'created_at' => '2026-10-19 23:59:59']);

    app(BuildDailyCenterStats::class)->execute(CarbonImmutable::parse('2026-10-20'));

    $row = analyticsCenterStats('2026-10-20', $center->id);

    expect((int) $row->gmv)->toBe(15000)
        ->and((int) $row->purchases_count)->toBe(2)
        ->and((int) $row->all_purchases_count)->toBe(4)
        ->and((int) $row->city_id)->toBe($center->city_id);
});

test('platform merchant purchases are stored under center 0', function (): void {
    AnalyticsFixtures::purchase(null, attributes: ['total_amount' => 20000, 'merchant_id' => 1, 'created_at' => '2026-10-20 09:00:00']);

    app(BuildDailyCenterStats::class)->execute(CarbonImmutable::parse('2026-10-20'));

    expect((int) analyticsCenterStats('2026-10-20', 0)->gmv)->toBe(20000);
});

test('fees, subsidies and refunds come from the ledger, dated by entry, in the platform view', function (): void {
    $center = CenterFactory::new()->create();
    $purchase = AnalyticsFixtures::purchase($center, attributes: ['created_at' => '2026-10-01 09:00:00']);
    $entry = fn (CenterLedgerEntryType $type, int $amount, string $at) => CenterLedgerEntryFactory::new()->create([
        'center_id' => $center->id, 'purchase_id' => $purchase->id, 'type' => $type, 'amount' => $amount, 'created_at' => $at,
    ]);

    $entry(CenterLedgerEntryType::PlatformFee, -1000, '2026-10-20 10:00:00');
    $entry(CenterLedgerEntryType::FeeReversal, 400, '2026-10-20 11:00:00');
    $entry(CenterLedgerEntryType::PlatformSubsidy, 300, '2026-10-20 11:00:00');
    $entry(CenterLedgerEntryType::Refund, -2500, '2026-10-20 12:00:00');
    $entry(CenterLedgerEntryType::Payment, 9000, '2026-10-20 12:00:00');
    $entry(CenterLedgerEntryType::PlatformFee, -700, '2026-10-21 01:00:00');

    app(BuildDailyCenterStats::class)->execute(CarbonImmutable::parse('2026-10-20'));

    $row = analyticsCenterStats('2026-10-20', $center->id);

    expect((int) $row->net_fee)->toBe(600)
        ->and((int) $row->subsidy)->toBe(300)
        ->and((int) $row->refunds)->toBe(2500)
        ->and((int) $row->gmv)->toBe(0);
});

test('online payment attempts count succeeded, refunded and failed outcomes; cash is left out', function (): void {
    $center = CenterFactory::new()->create();
    $purchase = AnalyticsFixtures::purchase($center, attributes: ['created_at' => '2026-10-20 09:00:00']);
    $payment = fn (PaymentMethod $method, PaymentStatus $status) => PaymentFactory::new()->create([
        'purchase_id' => $purchase->id, 'method' => $method, 'status' => $status, 'created_at' => '2026-10-20 09:05:00',
    ]);

    $payment(PaymentMethod::Online, PaymentStatus::Failed);
    $payment(PaymentMethod::Online, PaymentStatus::Expired);
    $payment(PaymentMethod::Online, PaymentStatus::Succeeded);
    $payment(PaymentMethod::Online, PaymentStatus::Refunded);
    $payment(PaymentMethod::CashOnArrival, PaymentStatus::Succeeded);

    app(BuildDailyCenterStats::class)->execute(CarbonImmutable::parse('2026-10-20'));

    $row = analyticsCenterStats('2026-10-20', $center->id);
    $methods = DB::table('analytics_daily_breakdowns')->where('dimension', 'payment_method')->pluck('count', 'dimension_key');

    expect((int) $row->payments_attempted)->toBe(4)
        ->and((int) $row->payments_succeeded)->toBe(2)
        ->and((int) $row->payments_failed)->toBe(2)
        ->and($methods->map(fn ($count) => (int) $count)->sortKeys()->all())->toBe(['cash_on_arrival' => 1, 'online' => 4]);
});

test('reservation outcomes are dated by service date and bookings by creation date', function (): void {
    $service = ServiceFactory::new()->create();
    $client = ClientFactory::new()->create();
    $reservation = fn (ReservationStatus $status, array $attributes = []) => ReservationFactory::new()->create([
        'service_id' => $service->id, 'status' => $status, 'date' => '2026-10-20', 'created_at' => '2026-10-10 09:00:00', ...$attributes,
    ]);

    $reservation(ReservationStatus::Completed, ['quantity' => 3]);
    $reservation(ReservationStatus::NoShow);
    $reservation(ReservationStatus::Cancelled, ['cancelled_by_type' => $client->getMorphClass(), 'cancelled_by_id' => $client->id]);
    $reservation(ReservationStatus::Cancelled);
    $reservation(ReservationStatus::Confirmed, ['date' => '2026-10-25', 'created_at' => '2026-10-20 08:00:00']);

    app(BuildDailyCenterStats::class)->execute(CarbonImmutable::parse('2026-10-20'));

    $row = analyticsCenterStats('2026-10-20', $service->center_id);

    expect((int) $row->reservations_scheduled)->toBe(4)
        ->and((int) $row->reservations_completed)->toBe(1)
        ->and((int) $row->reservations_no_show)->toBe(1)
        ->and((int) $row->reservations_cancelled)->toBe(2)
        ->and((int) $row->cancellations_by_client)->toBe(1)
        ->and((int) $row->bookings_made)->toBe(1)
        ->and((int) $row->tickets_booked)->toBe(1);
});

test('rebuilding a day replaces its rows, picking up later cancellations', function (): void {
    $center = CenterFactory::new()->create();
    $purchase = AnalyticsFixtures::purchase($center, attributes: ['total_amount' => 10000, 'created_at' => '2026-10-20 10:00:00']);
    $build = fn () => app(BuildDailyCenterStats::class)->execute(CarbonImmutable::parse('2026-10-20'));

    $build();
    $build();

    expect(DB::table('analytics_daily_center_stats')->count())->toBe(1)
        ->and((int) analyticsCenterStats('2026-10-20', $center->id)->gmv)->toBe(10000);

    $purchase->forceFill(['status' => PurchaseStatus::Cancelled])->save();
    $build();

    expect((int) analyticsCenterStats('2026-10-20', $center->id)->gmv)->toBe(0)
        ->and((int) analyticsCenterStats('2026-10-20', $center->id)->all_purchases_count)->toBe(1);
});

test('new buyers are counted on the day of their first counted purchase, platform-wide and per center', function (): void {
    $first = CenterFactory::new()->create();
    $second = CenterFactory::new()->create();
    $returning = ClientFactory::new()->create();
    $newcomer = ClientFactory::new()->create();

    AnalyticsFixtures::purchase($first, $returning, ['created_at' => '2026-10-05 10:00:00']);
    AnalyticsFixtures::purchase($second, $returning, ['created_at' => '2026-10-20 10:00:00']);
    AnalyticsFixtures::purchase($first, $newcomer, ['created_at' => '2026-10-20 11:00:00']);
    AnalyticsFixtures::purchase($first, $newcomer, ['created_at' => '2026-10-20 12:00:00']);

    app(BuildDailyCenterStats::class)->execute(CarbonImmutable::parse('2026-10-20'));
    app(BuildDailyPlatformStats::class)->execute(CarbonImmutable::parse('2026-10-20'));

    $platform = DB::table('analytics_daily_platform_stats')->where('date', '2026-10-20')->first();

    expect((int) $platform->new_buyers)->toBe(1)
        ->and((int) $platform->active_buyers_1d)->toBe(2)
        ->and((int) $platform->active_buyers_30d)->toBe(2)
        ->and((int) $platform->active_centers_30d)->toBe(2)
        ->and((int) analyticsCenterStats('2026-10-20', $first->id)->new_buyers)->toBe(1)
        ->and((int) analyticsCenterStats('2026-10-20', $second->id)->new_buyers)->toBe(1);
});

test('center-state and payout snapshots are written for yesterday and kept on older days', function (): void {
    CenterFactory::new()->create();
    CenterFactory::new()->blocked()->create();

    app(BuildDailyPlatformStats::class)->execute(CarbonImmutable::parse('2026-10-20'));
    app(BuildDailyPlatformStats::class)->execute(CarbonImmutable::parse('2026-10-19'));

    $yesterday = DB::table('analytics_daily_platform_stats')->where('date', '2026-10-20')->first();
    $older = DB::table('analytics_daily_platform_stats')->where('date', '2026-10-19')->first();

    expect((int) $yesterday->centers_active)->toBe(1)
        ->and((int) $yesterday->centers_blocked)->toBe(1)
        ->and((int) $yesterday->payout_unpaid_balance)->toBe(0)
        ->and($older->centers_active)->toBeNull()
        ->and($older->payout_unpaid_balance)->toBeNull();
});

test('monthly stats and cohort retention follow each client\'s purchases', function (): void {
    $center = CenterFactory::new()->create();
    $loyal = ClientFactory::new()->create(['created_at' => '2026-08-01 00:00:00']);
    $oneOff = ClientFactory::new()->create(['created_at' => '2026-08-02 00:00:00']);

    AnalyticsFixtures::purchase($center, $loyal, ['created_at' => '2026-08-10 10:00:00']);
    AnalyticsFixtures::purchase($center, $oneOff, ['created_at' => '2026-08-11 10:00:00']);
    AnalyticsFixtures::purchase($center, $loyal, ['created_at' => '2026-10-03 10:00:00']);
    AnalyticsFixtures::purchase($center, $oneOff, ['created_at' => '2026-10-04 10:00:00', 'status' => PurchaseStatus::Cancelled]);

    app(BuildClientActivity::class)->execute();

    $months = DB::table('analytics_monthly_client_stats')->orderBy('month')->get()
        ->keyBy(fn (object $row): string => substr($row->month, 0, 10));
    $cohort = DB::table('analytics_cohort_retention')->where('cohort_month', '2026-08-01')->orderBy('months_since')
        ->get()->map(fn (object $row): array => [(int) $row->months_since, (int) $row->cohort_size, (int) $row->active_buyers])->all();

    $buyers = $months->map(fn (object $row): array => [(int) $row->buyers, (int) $row->new_buyers, (int) $row->repeat_buyers])->all();

    expect($buyers)->toBe(['2026-08-01' => [2, 2, 0], '2026-10-01' => [1, 0, 1]])
        ->and((int) $months['2026-08-01']->signups)->toBe(2)
        ->and($cohort)->toBe([[0, 2, 2], [1, 2, 0], [2, 2, 1]]);
});

test('the command restates the configured window and refuses today', function (): void {
    config(['analytics.restate_days' => 3]);

    $this->artisan('analytics:build')->assertSuccessful();

    expect(DB::table('analytics_daily_platform_stats')->orderBy('date')->pluck('date')->map(fn ($date) => substr($date, 0, 10))->all())
        ->toBe(['2026-10-18', '2026-10-19', '2026-10-20']);

    $this->artisan('analytics:build', ['--to' => '2026-10-21'])->assertFailed();
});

test('a confirmed financial baseline corrects fees, GMV and refunds on its cutoff day only', function (): void {
    $center = CenterFactory::new()->create();
    AnalyticsFixtures::purchase($center, attributes: ['total_amount' => 10000, 'created_at' => '2026-10-19 10:00:00']);
    CenterFinancialBaselineFactory::new()->confirmed()->create([
        'center_id' => $center->id,
        'cutoff_at' => '2026-10-19 21:00:00',
        'target_platform_fees' => 1500,
        'migrated_platform_fees' => 1000,
        'target_gross_sales' => 9000,
        'migrated_gross_sales' => 10000,
        'target_refunds' => null,
        'migrated_refunds' => 700,
    ]);
    CenterLedgerEntryFactory::new()->create([
        'center_id' => $center->id, 'purchase_id' => null, 'type' => CenterLedgerEntryType::BaselineAdjustment, 'amount' => -4000, 'created_at' => '2026-10-20 10:00:00',
    ]);

    app(BuildDailyCenterStats::class)->execute(CarbonImmutable::parse('2026-10-19'));
    app(BuildDailyCenterStats::class)->execute(CarbonImmutable::parse('2026-10-20'));

    $cutoffDay = analyticsCenterStats('2026-10-19', $center->id);

    expect((int) $cutoffDay->net_fee)->toBe(500)
        ->and((int) $cutoffDay->gmv)->toBe(9000)
        ->and((int) $cutoffDay->refunds)->toBe(0)
        ->and(analyticsCenterStats('2026-10-20', $center->id))->toBeNull();
});

test('a pending financial baseline corrects nothing', function (): void {
    $center = CenterFactory::new()->create();
    CenterFinancialBaselineFactory::new()->create([
        'center_id' => $center->id,
        'cutoff_at' => '2026-10-19 21:00:00',
        'target_platform_fees' => 1500,
        'migrated_platform_fees' => 1000,
    ]);

    app(BuildDailyCenterStats::class)->execute(CarbonImmutable::parse('2026-10-19'));

    expect(analyticsCenterStats('2026-10-19', $center->id))->toBeNull();
});
