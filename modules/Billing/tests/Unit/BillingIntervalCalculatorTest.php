<?php

use Carbon\Carbon;
use Modules\Billing\Database\Factories\PlanFactory;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Services\BillingIntervalCalculator;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

test('nextBillingDate monthly count 3 preserves day with no overflow', function (): void {
    $from = Carbon::parse('2026-01-15 00:00:00');
    $calc = app(BillingIntervalCalculator::class);
    $result = $calc->nextBillingDate($from, BillingInterval::Monthly, 3);
    expect($result->toDateTimeString())->toBe('2026-04-15 00:00:00');
});

test('nextBillingDate yearly advances exactly one year', function (): void {
    $from = Carbon::parse('2026-02-29 10:30:00');
    $calc = app(BillingIntervalCalculator::class);
    $result = $calc->nextBillingDate($from, BillingInterval::Yearly, 1);
    expect($result->year)->toBe(2027);
    expect($result->month)->toBe(3);
    expect($result->day)->toBe(1);
    expect($result->hour)->toBe(10);
    expect($result->minute)->toBe(30);
});

test('trialEndsAt returns null when trial_days is zero', function (): void {
    $plan = PlanFactory::new()->withTrial(0)->create();
    $calc = app(BillingIntervalCalculator::class);
    $result = $calc->trialEndsAt($plan);
    expect($result)->toBeNull();
});

test('trialEndsAt adds trial_days to from date exactly', function (): void {
    $plan = PlanFactory::new()->withTrial(14)->create();
    $from = Carbon::parse('2026-01-15 09:00:00');
    $result = app(BillingIntervalCalculator::class)->trialEndsAt($plan, $from);
    expect($result)->not()->toBeNull();
    expect($result->toDateTimeString())->toBe('2026-01-29 09:00:00');
});

test('trialEndsAt uses now as default from when omitted', function (): void {
    $plan = PlanFactory::new()->withTrial(7)->create();
    $before = now()->addDays(7);
    $result = app(BillingIntervalCalculator::class)->trialEndsAt($plan);
    $after = now()->addDays(7);
    expect($result->gte($before->subSecond()))->toBeTrue();
    expect($result->lte($after->addSecond()))->toBeTrue();
});
