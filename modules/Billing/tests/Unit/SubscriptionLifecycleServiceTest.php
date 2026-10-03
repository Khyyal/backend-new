<?php

use Illuminate\Support\Facades\Event;
use Modules\Billing\Database\Factories\PlanFactory;
use Modules\Billing\Database\Factories\SubscriptionFactory;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Events\SubscriptionCanceled;
use Modules\Billing\Events\SubscriptionCreated;
use Modules\Billing\Events\SubscriptionRenewed;
use Modules\Billing\Exceptions\SubscriptionIneligibleException;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Services\SubscriptionLifecycleService;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Support\Database\Factories\CityFactory;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    CityFactory::new()->create();
    Event::fake();
});

test('subscribe creates trialing subscription with dates and dispatches SubscriptionCreated after commit', function (): void {
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->monthly()->active()->withTrial(14)->create(['trial_days' => 14]);
    $life = app(SubscriptionLifecycleService::class);

    $startsAtBefore = now();
    $sub = $life->subscribe($center, $plan);
    $endsAtExpected = $startsAtBefore->copy()->addMonthsNoOverflow(1);

    expect($sub->subscribable_type)->toBe($center->getMorphClass());
    expect($sub->subscribable_id)->toBe($center->id);
    expect($sub->plan_id)->toBe($plan->id);
    expect($sub->status)->toBe(SubscriptionStatus::Trialing);
    expect($sub->trial_ends_at)->not()->toBeNull();
    expect((int) $sub->trial_ends_at->diffInDays($sub->starts_at, true))->toBe(14);
    expect($sub->ends_at)->not()->toBeNull();
    expect($sub->ends_at->gte($endsAtExpected->subMinute()))->toBeTrue();

    Event::assertDispatched(SubscriptionCreated::class, 1);
});

test('subscribe creates active status directly when plan has no trial', function (): void {
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->monthly()->active()->withTrial(0)->create();
    $life = app(SubscriptionLifecycleService::class);

    $sub = $life->subscribe($center, $plan);
    expect($sub->status)->toBe(SubscriptionStatus::Active);
    expect($sub->trial_ends_at)->toBeNull();
    Event::assertDispatched(SubscriptionCreated::class, 1);
});

test('subscribe throws SubscriptionIneligibleException when duplicate plan active subscription exists', function (): void {
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->active()->create();
    $life = app(SubscriptionLifecycleService::class);

    $life->subscribe($center, $plan);
    $this->expectException(SubscriptionIneligibleException::class);
    $life->subscribe($center, $plan);
});

test('cancel at period end sets status canceled canceled_at and preserves ends_at', function (): void {
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->active()->create();
    $origEnds = now()->addMonthsNoOverflow(1)->startOfSecond();
    $sub = SubscriptionFactory::new()
        ->forCenter($center)
        ->active()
        ->create(['plan_id' => $plan->id, 'ends_at' => $origEnds]);

    $life = app(SubscriptionLifecycleService::class);
    $canceled = $life->cancel($sub, false);

    expect($canceled->status)->toBe(SubscriptionStatus::Canceled);
    expect($canceled->canceled_at)->not()->toBeNull();
    expect($canceled->ends_at->toDateTimeString() === $origEnds->toDateTimeString())->toBeTrue();
    Event::assertDispatched(SubscriptionCanceled::class, 1);
});

test('cancel immediately also sets ends_at to now', function (): void {
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->active()->create();
    $sub = SubscriptionFactory::new()
        ->forCenter($center)
        ->trialing()
        ->create(['plan_id' => $plan->id, 'ends_at' => now()->addYear()]);

    $before = now()->subSecond();
    $life = app(SubscriptionLifecycleService::class);
    $result = $life->cancel($sub, true);
    $after = now()->addSecond();

    expect($result->status)->toBe(SubscriptionStatus::Canceled);
    expect($result->canceled_at)->not()->toBeNull();
    expect($result->ends_at->between($before, $after))->toBeTrue();
});

test('renew creates new subscription with starts_at matching previous ends_at', function (): void {
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->monthly()->active()->create();
    $orig = SubscriptionFactory::new()
        ->forCenter($center)
        ->active()
        ->create(['plan_id' => $plan->id, 'ends_at' => now()->addDays(10)]);

    $life = app(SubscriptionLifecycleService::class);
    $renewed = $life->renew($orig);

    expect($renewed->id)->not()->toBe($orig->id);
    expect($renewed->plan_id)->toBe($plan->id);
    expect($renewed->subscribable_type)->toBe($center->getMorphClass());
    expect($renewed->subscribable_id)->toBe($center->id);
    expect($renewed->starts_at->eq($orig->ends_at))->toBeTrue();
    expect($renewed->status)->toBe(SubscriptionStatus::Active);
    expect($renewed->ends_at->eq($orig->ends_at->copy()->addMonthsNoOverflow(1)))->toBeTrue();

    Event::assertDispatched(SubscriptionRenewed::class, 1);
});

test('expire sets status to expired without event dispatch', function (): void {
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->active()->create();
    $sub = SubscriptionFactory::new()
        ->forCenter($center)
        ->active()
        ->endsAtPast()
        ->create(['plan_id' => $plan->id]);

    $life = app(SubscriptionLifecycleService::class);
    $expired = $life->expire($sub);

    expect($expired->status)->toBe(SubscriptionStatus::Expired);
    Event::assertNotDispatched(\Modules\Billing\Events\PlanCreated::class);
});

test('subscribe with bypassEligibility true skips duplicate check', function (): void {
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->active()->create();
    $life = app(SubscriptionLifecycleService::class);

    $s1 = $life->subscribe($center, $plan);
    $s2 = $life->subscribe($center, $plan, null, true);

    $this->assertDatabaseCount('subscriptions', 2);
    expect($s1->plan_id)->toBe($plan->id);
    expect($s2->plan_id)->toBe($plan->id);
});
