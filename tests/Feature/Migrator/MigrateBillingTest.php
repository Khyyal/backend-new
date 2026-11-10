<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Database\Factories\PlanFactory;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Modules\Centers\Database\Factories\CenterFactory;

require_once __DIR__.'/LegacySchema.php';

uses(RefreshDatabase::class);

/**
 * The new subscription migrated from a legacy subscription id.
 */
function migratedSubscription(int $legacyId): Subscription
{
    $id = DB::table('legacy_id_maps')->where('entity', 'subscription')->where('legacy_id', $legacyId)->value('new_id');

    return Subscription::query()->findOrFail($id);
}

/**
 * The new plan migrated from a legacy account plan id.
 */
function migratedPlan(int $legacyId): Plan
{
    $id = DB::table('legacy_id_maps')->where('entity', 'plan')->where('legacy_id', $legacyId)->value('new_id');

    return Plan::query()->findOrFail($id);
}

beforeEach(function (): void {
    useLegacyServiceSchema();

    CenterFactory::new()->create(['id' => 6]);
    $this->servicesLimitId = DB::table('limits')->insertGetId(['key' => 'services', 'name' => 'Services', 'created_at' => now(), 'updated_at' => now()]);
    $this->seededBasic = PlanFactory::new()->create(['slug' => 'basic', 'price' => 19900]);

    DB::connection('legacy')->table('account_plans')->insert([
        ['id' => 5, 'name' => 'trial', 'features' => '{"duration": 14, "max_services": 30, "can_edit_booking": false}', 'monthly_price' => 0, 'yearly_price' => 0, 'description' => 'Try Khyyal free', 'deleted_at' => '2026-07-05 19:26:36'],
        ['id' => 6, 'name' => 'basic', 'features' => '{"max_services": 3}', 'monthly_price' => 120, 'yearly_price' => 1152, 'description' => null, 'deleted_at' => null],
        ['id' => 10, 'name' => 'growth', 'features' => '{"max_services": null, "platform_commission": 6}', 'monthly_price' => 210.5, 'yearly_price' => 3600, 'description' => null, 'deleted_at' => null],
    ]);

    $subscription = ['price_at_purchase' => 0, 'cancelled_at' => null, 'billing_cycle' => 'monthly', 'ends_at' => '2026-03-18', 'starts_at' => '2026-02-18'];

    DB::connection('legacy')->table('subscriptions')->insert([
        [...$subscription, 'id' => 22, 'barn_id' => 6, 'account_plan_id' => 10, 'plan_name' => 'growth', 'status' => 'active'],
        [...$subscription, 'id' => 23, 'barn_id' => 6, 'account_plan_id' => 5, 'plan_name' => 'trial', 'status' => 'active'],
        [...$subscription, 'id' => 24, 'barn_id' => 6, 'account_plan_id' => 6, 'plan_name' => 'basic', 'status' => 'cancelled', 'cancelled_at' => '2026-03-01 10:00:00'],
        [...$subscription, 'id' => 25, 'barn_id' => 6, 'account_plan_id' => 10, 'plan_name' => 'growth', 'status' => 'pending'],
        [...$subscription, 'id' => 26, 'barn_id' => 6, 'account_plan_id' => 10, 'plan_name' => 'growth', 'status' => 'expired', 'billing_cycle' => 'yearly', 'ends_at' => null],
        [...$subscription, 'id' => 27, 'barn_id' => 999, 'account_plan_id' => 10, 'plan_name' => 'growth', 'status' => 'active'],
    ]);
});

test('legacy plans become monthly plans priced in halalas with their trial and services limit', function (): void {
    $this->artisan('migrator:plans')->assertSuccessful();

    $growth = migratedPlan(10);
    $trial = migratedPlan(5);

    expect($growth->slug)->toBe('growth')
        ->and($growth->name)->toBe(['en' => 'Growth', 'ar' => 'Growth'])
        ->and($growth->price)->toBe(21050)
        ->and($growth->billing_interval)->toBe(BillingInterval::Monthly)
        ->and($growth->status)->toBe(PlanStatus::Active)
        ->and($growth->limits()->count())->toBe(0)
        ->and($trial->status)->toBe(PlanStatus::InActive)
        ->and($trial->trial_days)->toBe(14)
        ->and($trial->description)->toBe(['en' => 'Try Khyyal free'])
        ->and(DB::table('plan_limits')->where('plan_id', $trial->id)->where('limit_id', $this->servicesLimitId)->value('value'))->toBe(30);
});

test('a legacy plan whose slug already exists is mapped onto that plan without changing it', function (): void {
    $this->artisan('migrator:plans')->assertSuccessful();

    expect(migratedPlan(6)->id)->toBe($this->seededBasic->id)
        ->and($this->seededBasic->fresh()->price)->toBe(19900)
        ->and($this->seededBasic->limits()->count())->toBe(0);
});

test('subscriptions are copied onto the mapped plans with their mapped status', function (): void {
    $this->artisan('migrator:plans')->assertSuccessful();
    $this->artisan('migrator:subscriptions')
        ->expectsOutputToContain('Subscription #27: barn #999 was not migrated')
        ->assertSuccessful();

    $active = migratedSubscription(22);
    $trial = migratedSubscription(23);
    $canceled = migratedSubscription(24);

    expect($active->status)->toBe(SubscriptionStatus::Active)
        ->and($active->plan_id)->toBe(migratedPlan(10)->id)
        ->and($active->subscribable_type)->toBe('center')
        ->and($active->subscribable_id)->toBe(6)
        ->and($active->starts_at->toDateTimeString())->toBe('2026-02-18 00:00:00')
        ->and($active->ends_at->toDateString())->toBe('2026-03-18')
        ->and($trial->status)->toBe(SubscriptionStatus::Trialing)
        ->and($trial->trial_ends_at->toDateString())->toBe('2026-03-18')
        ->and($canceled->status)->toBe(SubscriptionStatus::Canceled)
        ->and($canceled->plan_id)->toBe($this->seededBasic->id)
        ->and($canceled->canceled_at->toDateTimeString())->toBe('2026-03-01 10:00:00');
});

test('pending checkouts are skipped and a missing end date follows the billing cycle', function (): void {
    $this->artisan('migrator:plans')->assertSuccessful();
    $this->artisan('migrator:subscriptions')->assertSuccessful();

    expect(DB::table('legacy_id_maps')->where('entity', 'subscription')->where('legacy_id', 25)->exists())->toBeFalse()
        ->and(migratedSubscription(26)->status)->toBe(SubscriptionStatus::Expired)
        ->and(migratedSubscription(26)->ends_at->toDateString())->toBe('2027-02-18');
});

test('re-running creates no extra plans or subscriptions', function (): void {
    $this->artisan('migrator:plans')->assertSuccessful();
    $this->artisan('migrator:subscriptions')->assertSuccessful();
    $this->artisan('migrator:plans')->assertSuccessful();
    $this->artisan('migrator:subscriptions')->assertSuccessful();

    expect(DB::table('plans')->count())->toBe(3)
        ->and(DB::table('plan_limits')->count())->toBe(1)
        ->and(DB::table('subscriptions')->count())->toBe(4);
});
