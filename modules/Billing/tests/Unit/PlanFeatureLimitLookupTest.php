<?php

use Modules\Billing\Database\Factories\FeatureFactory;
use Modules\Billing\Database\Factories\LimitFactory;
use Modules\Billing\Database\Factories\PlanFactory;
use Modules\Billing\Services\PlanFeatureLimitLookup;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    \Modules\Support\Database\Factories\CityFactory::new()->create();
});

test('planHasFeatureEnabled returns true for attached enabled features', function (): void {
    $f1 = FeatureFactory::new()->create(['key' => 'feat_a']);
    $f2 = FeatureFactory::new()->create(['key' => 'feat_b']);
    $f3 = FeatureFactory::new()->create(['key' => 'feat_c']);
    $plan = PlanFactory::new()
        ->withFeature($f1, true)
        ->withFeature($f2, true)
        ->create();

    $lookup = app(PlanFeatureLimitLookup::class);

    expect($lookup->planHasFeatureEnabled($plan, 'feat_a'))->toBeTrue();
    expect($lookup->planHasFeatureEnabled($plan, 'feat_b'))->toBeTrue();
    expect($lookup->planHasFeatureEnabled($plan, 'feat_c'))->toBeFalse();
    expect($lookup->planHasFeatureEnabled($plan, 'absent'))->toBeFalse();
});

test('getFeatures returns correct shape with all fields', function (): void {
    $fA = FeatureFactory::new()->create(['key' => 'a', 'name' => 'A', 'is_quota' => false]);
    $fB = FeatureFactory::new()->create(['key' => 'b', 'name' => 'B', 'is_quota' => true]);
    $plan = PlanFactory::new()
        ->withFeature($fA, true)
        ->withFeature($fB, false)
        ->create();

    $features = app(PlanFeatureLimitLookup::class)->getFeatures($plan);
    expect($features)->toHaveCount(2);
    $keys = $features->pluck('key')->all();
    sort($keys);
    expect($keys)->toBe(['a', 'b']);

    $byKey = $features->keyBy('key');
    expect($byKey['a']->enabled)->toBeTrue();
    expect($byKey['a']->is_quota)->toBeFalse();
    expect($byKey['b']->enabled)->toBeFalse();
    expect($byKey['b']->is_quota)->toBeTrue();
});

test('getAllLimits returns collection with key name value shape', function (): void {
    $l1 = LimitFactory::new()->create(['key' => 'quota_users', 'name' => 'Users']);
    $l2 = LimitFactory::new()->create(['key' => 'quota_storage', 'name' => 'Storage']);
    $plan = PlanFactory::new()
        ->withLimit($l1, 10)
        ->withLimit($l2, 500)
        ->create();

    $all = app(PlanFeatureLimitLookup::class)->getAllLimits($plan);
    expect($all)->toHaveCount(2);
    $byKey = $all->keyBy('key');
    expect($byKey['quota_users']->value)->toBe(10);
    expect($byKey['quota_users']->name)->toBe('Users');
    expect($byKey['quota_storage']->value)->toBe(500);
});

test('getLimitValue returns default 0 when limit absent', function (): void {
    $l1 = LimitFactory::new()->create(['key' => 'present']);
    $plan = PlanFactory::new()->withLimit($l1, 33)->create();
    $lookup = app(PlanFeatureLimitLookup::class);

    expect($lookup->getLimitValue($plan, 'present'))->toBe(33);
    expect($lookup->getLimitValue($plan, 'absent'))->toBe(0);
    expect($lookup->getLimitValue($plan, 'absent', 5))->toBe(5);
});
