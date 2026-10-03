<?php

use Modules\Promotion\Enums\ApplicationMethod;
use Modules\Promotion\Enums\DiscountScope;
use Modules\Promotion\Enums\DiscountType;
use Modules\Promotion\Enums\PromotionStatus;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

test('discount type enum has exact cases', function (): void {
    expect(DiscountType::cases())->toHaveCount(2);
    expect(DiscountType::Percentage->value)->toBe('percentage');
    expect(DiscountType::Fixed->value)->toBe('fixed');
});

test('discount scope enum has exact cases', function (): void {
    expect(DiscountScope::cases())->toHaveCount(2);
    expect(DiscountScope::All->value)->toBe('all');
    expect(DiscountScope::SpecificItems->value)->toBe('specific_items');
});

test('application method enum has exact cases', function (): void {
    expect(ApplicationMethod::cases())->toHaveCount(2);
    expect(ApplicationMethod::Automatic->value)->toBe('automatic');
    expect(ApplicationMethod::Coupon->value)->toBe('coupon');
});

test('promotion status enum has exact cases', function (): void {
    expect(PromotionStatus::cases())->toHaveCount(2);
    expect(PromotionStatus::Active->value)->toBe('active');
    expect(PromotionStatus::InActive->value)->toBe('in_active');
});

test('all enums are backed enums', function (): void {
    foreach ([DiscountType::class, DiscountScope::class, ApplicationMethod::class, PromotionStatus::class] as $e) {
        expect(is_subclass_of($e, BackedEnum::class))->toBeTrue();
    }
});
