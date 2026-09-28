<?php

use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Enums\SubscriptionAction;
use Modules\Billing\Enums\SubscriptionStatus;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

test('billing interval enum has exact cases', function (): void {
    expect(BillingInterval::cases())->toHaveCount(2);
    expect(BillingInterval::Monthly->value)->toBe('monthly');
    expect(BillingInterval::Yearly->value)->toBe('yearly');
});

test('plan status enum has exact cases', function (): void {
    expect(PlanStatus::cases())->toHaveCount(2);
    expect(PlanStatus::Active->value)->toBe('active');
    expect(PlanStatus::InActive->value)->toBe('in_active');
});

test('subscription status enum has exact cases', function (): void {
    expect(SubscriptionStatus::cases())->toHaveCount(4);
    expect(SubscriptionStatus::Active->value)->toBe('active');
    expect(SubscriptionStatus::Canceled->value)->toBe('canceled');
    expect(SubscriptionStatus::Expired->value)->toBe('expired');
    expect(SubscriptionStatus::Trialing->value)->toBe('trialing');
});

test('subscription action enum has exact cases', function (): void {
    expect(SubscriptionAction::cases())->toHaveCount(4);
    expect(SubscriptionAction::Subscribe->value)->toBe('subscribe');
    expect(SubscriptionAction::Cancel->value)->toBe('cancel');
    expect(SubscriptionAction::Renew->value)->toBe('renew');
    expect(SubscriptionAction::Expire->value)->toBe('expire');
});

test('all four enums are backed enums', function (): void {
    foreach ([
        BillingInterval::class,
        PlanStatus::class,
        SubscriptionStatus::class,
        SubscriptionAction::class,
    ] as $e) {
        expect(is_subclass_of($e, BackedEnum::class))->toBeTrue("$e is not a BackedEnum");
    }
});
