<?php

use Modules\Billing\Database\Factories\PlanFactory;
use Modules\Billing\Exceptions\SubscriptionIneligibleException;
use Modules\Billing\Models\Plan;
use Modules\Billing\Services\SubscriptionLifecycleService;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Support\Database\Factories\CityFactory;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    CityFactory::new()->create();
});

test('two sequential subscribe calls on same plan + subscribable: exactly one success second throws SubscriptionIneligibleException', function (): void {
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->monthly()->active()->create(['trial_days' => 0]);
    $life = app(SubscriptionLifecycleService::class);

    $succeeded = 0;
    $threw = 0;

    try {
        Plan::query()->whereKey($plan->id)->lockForUpdate()->exists();
        $life->subscribe($center, $plan);
        $succeeded++;
    } catch (SubscriptionIneligibleException $e) {
        $threw++;
    }

    try {
        Plan::query()->whereKey($plan->id)->lockForUpdate()->exists();
        $life->subscribe($center, $plan);
        $succeeded++;
    } catch (SubscriptionIneligibleException $e) {
        $threw++;
    }

    expect($succeeded)->toBe(1);
    expect($threw)->toBe(1);
    $this->assertDatabaseCount('subscriptions', 1);
});
