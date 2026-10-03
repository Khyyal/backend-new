<?php

use Modules\Billing\Database\Factories\PlanFactory;
use Modules\Billing\Database\Factories\SubscriptionFactory;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Services\SubscriptionEligibilityService;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Support\Database\Factories\CityFactory;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    CityFactory::new()->create();
});

test('canSubscribe returns false for inactive plan', function (): void {
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->inactive()->create();
    $svc = app(SubscriptionEligibilityService::class);
    expect($svc->canSubscribe($center, $plan))->toBeFalse();
});

test('canSubscribe returns false when subscribable already has active or trialing subscription for same plan', function (): void {
    $center = CenterFactory::new()->create();
    $planActive = PlanFactory::new()->active()->create();
    $planOther = PlanFactory::new()->active()->create();
    $svc = app(SubscriptionEligibilityService::class);

    SubscriptionFactory::new()->forCenter($center)->trialing()->create(['plan_id' => $planActive->id]);

    expect($svc->canSubscribe($center, $planActive))->toBeFalse();
    expect($svc->canSubscribe($center, $planOther))->toBeTrue();
});

test('canCancel returns true only for active and trialing statuses', function (): void {
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->active()->create();
    $svc = app(SubscriptionEligibilityService::class);

    $active = SubscriptionFactory::new()->forCenter($center)->active()->create(['plan_id' => $plan->id]);
    $trialing = SubscriptionFactory::new()->forCenter($center)->trialing()->create(['plan_id' => $plan->id]);
    $canceled = SubscriptionFactory::new()->forCenter($center)->canceled()->create(['plan_id' => $plan->id]);
    $expired = SubscriptionFactory::new()->forCenter($center)->expired()->create(['plan_id' => $plan->id]);

    expect($svc->canCancel($active, $center))->toBeTrue();
    expect($svc->canCancel($trialing, $center))->toBeTrue();
    expect($svc->canCancel($canceled, $center))->toBeFalse();
    expect($svc->canCancel($expired, $center))->toBeFalse();
});

test('canCancel returns false when subscribable owner mismatch', function (): void {
    $centerA = CenterFactory::new()->create();
    $centerB = CenterFactory::new()->create();
    $plan = PlanFactory::new()->active()->create();
    $svc = app(SubscriptionEligibilityService::class);

    $sub = SubscriptionFactory::new()->forCenter($centerA)->active()->create(['plan_id' => $plan->id]);
    expect($svc->canCancel($sub, $centerB))->toBeFalse();
});

test('subscriptionCurrentlyValid with ends_at null counts as valid when status active', function (): void {
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->active()->create();
    $svc = app(SubscriptionEligibilityService::class);

    $sub = SubscriptionFactory::new()
        ->forCenter($center)
        ->active()
        ->create(['plan_id' => $plan->id, 'ends_at' => null]);

    expect($svc->subscriptionCurrentlyValid($sub))->toBeTrue();
});

test('subscriptionCurrentlyValid returns false when expired status or ends_at past', function (): void {
    $center = CenterFactory::new()->create();
    $plan = PlanFactory::new()->active()->create();
    $svc = app(SubscriptionEligibilityService::class);

    $expired = SubscriptionFactory::new()
        ->forCenter($center)
        ->expired()
        ->endsAtPast()
        ->create(['plan_id' => $plan->id]);

    $activePast = SubscriptionFactory::new()
        ->forCenter($center)
        ->create([
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
        ]);
    $activePast->ends_at = now()->subDay();
    $activePast->save();
    $activePast->refresh();

    expect($svc->subscriptionCurrentlyValid($expired))->toBeFalse();
    expect($svc->subscriptionCurrentlyValid($activePast))->toBeFalse();
});
