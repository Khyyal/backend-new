<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Promotion\Database\Factories\CouponFactory;
use Modules\Promotion\Database\Factories\DiscountFactory;
use Modules\Promotion\Enums\ApplicationMethod;
use Modules\Promotion\Enums\DiscountScope;
use Modules\Promotion\Enums\DiscountType;
use Modules\Promotion\Enums\PromotionStatus;
use Modules\Promotion\Models\Discount;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Support\Database\Factories\CityFactory;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    CityFactory::new()->create();
});

test('migrations create all four tables with expected columns', function (): void {
    foreach (['discounts', 'coupons', 'discount_service', 'discount_redemptions'] as $t) {
        expect(Schema::hasTable($t))->toBeTrue("$t table missing");
    }

    $discountCols = Schema::getColumnListing('discounts');
    $expected = [
        'id', 'center_id', 'name', 'description', 'type', 'value',
        'scope', 'application_method', 'minimum_amount', 'maximum_discount',
        'usage_limit', 'usage_limit_per_customer', 'is_stackable',
        'starts_at', 'ends_at', 'status',
    ];
    foreach ($expected as $col) {
        expect(in_array($col, $discountCols, true))->toBeTrue("discounts missing column $col");
    }

    $couponCols = Schema::getColumnListing('coupons');
    foreach (['discount_id', 'code', 'starts_at', 'ends_at', 'status'] as $c) {
        expect(in_array($c, $couponCols, true))->toBeTrue("coupons missing column $c");
    }

    $redemptionCols = Schema::getColumnListing('discount_redemptions');
    foreach (['discount_id', 'coupon_id', 'used_by_type', 'used_by_id', 'discountable_type', 'discountable_id', 'discount_amount', 'redeemed_at'] as $c) {
        expect(in_array($c, $redemptionCols, true))->toBeTrue("redemptions missing column $c");
    }
});

test('discount belongs to a center', function (): void {
    $center = CenterFactory::new()->create();
    $discount = DiscountFactory::new()->for($center)->create();

    $fresh = Discount::query()->findOrFail($discount->id);
    expect($fresh->center_id)->toBe($center->id);
    expect($fresh->center->is($center))->toBeTrue();
    expect($center->discounts()->pluck('id')->all())->toBe([$discount->id]);
});

test('multilingual name and description persist as arrays', function (): void {
    $name = ['en' => '10% Summer Sale', 'ar' => 'خصم الصيف 10٪'];
    $description = ['en' => 'End of season', 'ar' => 'نهاية الموسم'];

    $d = DiscountFactory::new()->create(compact('name', 'description'));
    $d->refresh();

    expect($d->name)->toBe($name);
    expect($d->description)->toBe($description);
});

test('specific items discount attaches services', function (): void {
    $discount = DiscountFactory::new()->specificItems()->create();
    $services = ServiceFactory::new()->count(3)->create(['center_id' => $discount->center_id]);

    $discount->services()->attach($services);

    expect($discount->services()->count())->toBe(3);
    expect($services->first()->discounts()->pluck('discounts.id')->all())->toBe([$discount->id]);
    $this->assertDatabaseCount('discount_service', 3);
});

test('coupon code unique constraint prevents duplicates', function (): void {
    $d = DiscountFactory::new()->create();
    CouponFactory::new()->for($d, 'discount')->create(['code' => 'SAVE10']);

    $this->expectException(UniqueConstraintViolationException::class);
    CouponFactory::new()->for($d, 'discount')->create(['code' => 'SAVE10']);
});

test('enum casts round-trip on discount and coupon', function (): void {
    $d = DiscountFactory::new()->create([
        'type' => DiscountType::Percentage,
        'scope' => DiscountScope::All,
        'application_method' => ApplicationMethod::Coupon,
        'status' => PromotionStatus::InActive,
    ]);
    $d->refresh();

    expect($d->type)->toBe(DiscountType::Percentage);
    expect($d->scope)->toBe(DiscountScope::All);
    expect($d->application_method)->toBe(ApplicationMethod::Coupon);
    expect($d->status)->toBe(PromotionStatus::InActive);

    $c = CouponFactory::new()->for($d, 'discount')->create(['status' => PromotionStatus::InActive]);
    $c->refresh();
    expect($c->status)->toBe(PromotionStatus::InActive);
});
