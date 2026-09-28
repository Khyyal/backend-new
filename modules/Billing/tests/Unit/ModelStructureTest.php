<?php

use Illuminate\Support\Facades\Schema;
use Modules\Billing\Database\Factories\FeatureFactory;
use Modules\Billing\Database\Factories\LimitFactory;
use Modules\Billing\Database\Factories\PlanFactory;
use Modules\Billing\Database\Factories\SubscriptionFactory;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanFeature;
use Modules\Billing\Models\PlanLimit;
use Modules\Billing\Models\Subscription;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Support\Database\Factories\CityFactory;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    CityFactory::new()->create();
});

test('all six billing tables exist with expected columns', function (): void {
    foreach (['plans', 'features', 'limits', 'plan_features', 'plan_limits', 'subscriptions'] as $t) {
        expect(Schema::hasTable($t))->toBeTrue("$t table missing");
    }

    $plansCols = Schema::getColumnListing('plans');
    foreach ([
        'id', 'name', 'slug', 'description', 'price', 'billing_interval',
        'display_features', 'status', 'trial_days',
    ] as $c) {
        expect(in_array($c, $plansCols, true))->toBeTrue("plans missing column $c");
    }

    $featuresCols = Schema::getColumnListing('features');
    foreach (['id', 'key', 'name', 'is_quota'] as $c) {
        expect(in_array($c, $featuresCols, true))->toBeTrue("features missing column $c");
    }

    $limitsCols = Schema::getColumnListing('limits');
    foreach (['id', 'key', 'name'] as $c) {
        expect(in_array($c, $limitsCols, true))->toBeTrue("limits missing column $c");
    }

    $pfCols = Schema::getColumnListing('plan_features');
    foreach (['id', 'plan_id', 'feature_id', 'enabled'] as $c) {
        expect(in_array($c, $pfCols, true))->toBeTrue("plan_features missing column $c");
    }

    $plCols = Schema::getColumnListing('plan_limits');
    foreach (['id', 'plan_id', 'limit_id', 'value'] as $c) {
        expect(in_array($c, $plCols, true))->toBeTrue("plan_limits missing column $c");
    }

    $subCols = Schema::getColumnListing('subscriptions');
    foreach ([
        'id', 'subscribable_type', 'subscribable_id', 'plan_id', 'status',
        'trial_ends_at', 'starts_at', 'ends_at', 'canceled_at',
    ] as $c) {
        expect(in_array($c, $subCols, true))->toBeTrue("subscriptions missing column $c");
    }
});

test('six-step rollback drops all six billing tables then remigrates', function (): void {
    $this->artisan('migrate:rollback', ['--step' => 6])->assertExitCode(0);
    foreach (['plans', 'features', 'limits', 'plan_features', 'plan_limits', 'subscriptions'] as $t) {
        expect(Schema::hasTable($t))->toBeFalse("$t still exists after 6-step rollback");
    }
    $this->artisan('migrate')->assertExitCode(0);
    foreach (['plans', 'features', 'limits', 'plan_features', 'plan_limits', 'subscriptions'] as $t) {
        expect(Schema::hasTable($t))->toBeTrue("$t missing after re-migrate");
    }
});

test('plan factory creates a valid plan with enum casts and trial_days', function (): void {
    $plan = PlanFactory::new()
        ->monthly()
        ->active()
        ->withTrial(21)
        ->create();
    $plan->refresh();

    expect($plan->billing_interval)->toBe(BillingInterval::Monthly);
    expect($plan->status)->toBe(PlanStatus::Active);
    expect($plan->trial_days)->toBe(21);
    expect($plan->price)->toBeNumeric();
    expect(is_array($plan->name))->toBeTrue();
    expect($plan->name)->toHaveKey('en');
});

test('plan withFeature and withLimit persist via pivot direct model reads', function (): void {
    $feature = FeatureFactory::new()->create();
    $limit = LimitFactory::new()->create();
    $plan = PlanFactory::new()
        ->withFeature($feature, true)
        ->withLimit($limit, 42)
        ->create();

    expect($plan->features()->count())->toBe(1);
    expect($plan->limits()->count())->toBe(1);
    expect($plan->planFeatures()->first()?->enabled)->toBeTrue();
    expect($plan->planLimits()->first()?->value)->toBe(42);
    expect((bool) $plan->features()->first()->pivot?->enabled)->toBeTrue();
    expect((int) $plan->limits()->first()->pivot?->value)->toBe(42);
});

test('subscription subscribable morph resolves correctly for center', function (): void {
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->active()->create();
    $sub = SubscriptionFactory::new()
        ->forCenter($center)
        ->create(['plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $sub->refresh();

    expect($sub->subscribable)->toBeInstanceOf(\Modules\Centers\Models\Center::class);
    expect($sub->subscribable->id)->toBe($center->id);
    expect($sub->plan)->toBeInstanceOf(Plan::class);
    expect($sub->status)->toBe(SubscriptionStatus::Active);
});

test('center HasSubscriptions trait methods resolve relationships and currentSubscription', function (): void {
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->active()->create();
    $plan2 = PlanFactory::new()->active()->create();

    SubscriptionFactory::new()
        ->forCenter($center)
        ->expired()
        ->create(['plan_id' => $plan2->id]);

    $active = SubscriptionFactory::new()
        ->forCenter($center)
        ->active()
        ->create(['plan_id' => $plan->id, 'starts_at' => now()]);

    $center->refresh();

    expect($center->subscriptions()->count())->toBe(2);
    expect($center->activeSubscriptions()->count())->toBe(1);
    $current = $center->currentSubscription();
    expect($current)->toBeInstanceOf(Subscription::class);
    expect($current->id)->toBe($active->id);
});

test('unique pivot triple constraints exist for plan_features and plan_limits', function (): void {
    $plan = PlanFactory::new()->create();
    $feature = FeatureFactory::new()->create();
    $limit = LimitFactory::new()->create();

    PlanFeature::query()->create(['plan_id' => $plan->id, 'feature_id' => $feature->id, 'enabled' => true]);
    PlanLimit::query()->create(['plan_id' => $plan->id, 'limit_id' => $limit->id, 'value' => 10]);

    $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
    PlanFeature::query()->create(['plan_id' => $plan->id, 'feature_id' => $feature->id, 'enabled' => false]);
});

test('slug unique constraint on plans exists', function (): void {
    PlanFactory::new()->create(['slug' => 'unique-slug-test']);
    $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
    PlanFactory::new()->create(['slug' => 'unique-slug-test']);
});
