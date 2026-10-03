<?php

use Illuminate\Support\Facades\Event;
use Modules\Billing\Database\Factories\FeatureFactory;
use Modules\Billing\Database\Factories\LimitFactory;
use Modules\Billing\Database\Factories\PlanFactory;
use Modules\Billing\Database\Factories\SubscriptionFactory;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Events\PlanCreated;
use Modules\Billing\Events\SubscriptionCanceled;
use Modules\Billing\Events\SubscriptionCreated;
use Modules\Billing\Events\SubscriptionRenewed;
use Modules\Billing\Models\Plan;
use Modules\Billing\Services\SubscriptionLifecycleService;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Support\Database\Factories\CityFactory;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    CityFactory::new()->create();
});

test('all four event classes are serializable via serialize', function (): void {
    $plan = PlanFactory::new()->create();
    $center = CenterFactory::new()->create();
    $sub = SubscriptionFactory::new()->forCenter($center)->create(['plan_id' => $plan->id]);

    $events = [
        new PlanCreated($plan),
        new SubscriptionCreated($sub),
        new SubscriptionCanceled($sub),
        new SubscriptionRenewed($sub),
    ];

    foreach ($events as $e) {
        $serialized = serialize($e);
        $restored = unserialize($serialized);
        expect($restored)->toBeInstanceOf(get_class($e));
    }
});

test('PlanCreated event dispatched once after commit via DB tx + afterCommit', function (): void {
    Event::fake();
    \Illuminate\Support\Facades\DB::transaction(function (): void {
        $plan = Plan::query()->create([
            'name' => ['en' => 'P', 'ar' => 'ب'],
            'slug' => 'event-plan-1',
            'price' => 99,
            'billing_interval' => \Modules\Billing\Enums\BillingInterval::Monthly,
            'status' => \Modules\Billing\Enums\PlanStatus::Active,
            'trial_days' => 0,
        ]);
        \Illuminate\Support\Facades\DB::afterCommit(fn () => event(new PlanCreated($plan)));
    });
    Event::assertDispatched(PlanCreated::class, 1);
});

test('SubscriptionCreated dispatched after subscribe via lifecycle service exactly once', function (): void {
    Event::fake();
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->active()->create();
    $life = app(SubscriptionLifecycleService::class);
    $life->subscribe($center, $plan);
    Event::assertDispatched(SubscriptionCreated::class, 1);
    Event::assertNotDispatched(SubscriptionCanceled::class);
    Event::assertNotDispatched(SubscriptionRenewed::class);
});

test('SubscriptionCanceled dispatched once after cancel', function (): void {
    Event::fake();
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->active()->create();
    $sub = SubscriptionFactory::new()->forCenter($center)->trialing()->create(['plan_id' => $plan->id]);
    $life = app(SubscriptionLifecycleService::class);
    $life->cancel($sub, false);
    Event::assertDispatched(SubscriptionCanceled::class, 1);
});

test('SubscriptionRenewed dispatched once after renew', function (): void {
    Event::fake();
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->active()->create();
    $orig = SubscriptionFactory::new()
        ->forCenter($center)
        ->active()
        ->create(['plan_id' => $plan->id, 'ends_at' => now()->addDays(5)]);
    $life = app(SubscriptionLifecycleService::class);
    $life->renew($orig);
    Event::assertDispatched(SubscriptionRenewed::class, 1);
});
