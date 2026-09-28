<?php

use Modules\Promotion\Database\Factories\CouponFactory;
use Modules\Promotion\Database\Factories\DiscountFactory;
use Modules\Promotion\Enums\ApplicationMethod;
use Modules\Promotion\Enums\DiscountScope;
use Modules\Promotion\Services\CouponValidator;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

function vCreate(array $overrides = []): \Modules\Promotion\Models\Discount
{
    return DiscountFactory::new()->create(array_merge([
        'application_method' => ApplicationMethod::Coupon,
        'status' => \Modules\Promotion\Enums\PromotionStatus::Active,
        'starts_at' => now()->subDay(),
        'ends_at' => null,
        'scope' => DiscountScope::All,
        'usage_limit' => null,
        'usage_limit_per_customer' => null,
    ], $overrides));
}

beforeEach(function (): void {
    $this->validator = app(CouponValidator::class);
});

test('missing code returns coupon_not_found', function (): void {
    $r = $this->validator->validate('MISSING');
    expect($r['valid'])->toBeFalse();
    expect($r['reason'])->toBe('coupon_not_found');
});

test('inactive coupon returns coupon_inactive', function (): void {
    $d = vCreate();
    CouponFactory::new()->for($d, 'discount')->inactive()->create(['code' => 'INACTIVE']);
    $r = $this->validator->validate('INACTIVE');
    expect($r['valid'])->toBeFalse();
    expect($r['reason'])->toBe('coupon_inactive');
});

test('expired / not-started coupon returns coupon_expired', function (): void {
    $d = vCreate();
    CouponFactory::new()->for($d, 'discount')->expired()->create(['code' => 'EXPIRED']);
    $r = $this->validator->validate('EXPIRED');
    expect($r['valid'])->toBeFalse();
    expect($r['reason'])->toBe('coupon_expired');

    CouponFactory::new()->for($d, 'discount')->notStarted()->create(['code' => 'FUTURE']);
    $r2 = $this->validator->validate('FUTURE');
    expect($r2['valid'])->toBeFalse();
    expect($r2['reason'])->toBe('coupon_expired');
});

test('valid coupon linked to ineligible discount returns discount_ineligible', function (): void {
    $d = vCreate(['minimum_amount' => 1000]);
    CouponFactory::new()->for($d, 'discount')->create(['code' => 'HIGHMIN']);

    $r = $this->validator->validate('HIGHMIN', null, 50);
    expect($r['valid'])->toBeFalse();
    expect($r['reason'])->toBe('discount_ineligible');
});

test('valid active coupon with eligible discount succeeds and is case-insensitive', function (): void {
    $d = vCreate(['minimum_amount' => 10]);
    $c = CouponFactory::new()->for($d, 'discount')->create(['code' => 'SAVE10']);

    $r = $this->validator->validate('save10', null, 100);
    expect($r['valid'])->toBeTrue();
    expect($r['coupon']->id)->toBe($c->id);
    expect($r['discount']->id)->toBe($d->id);

    $r2 = $this->validator->validate('   Save10  ', null, 100);
    expect($r2['valid'])->toBeTrue();
});
