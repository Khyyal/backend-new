<?php

use Illuminate\Support\Facades\Event;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Promotion\Database\Factories\CouponFactory;
use Modules\Promotion\Database\Factories\DiscountFactory;
use Modules\Promotion\Database\Factories\DiscountRedemptionFactory;
use Modules\Promotion\Enums\ApplicationMethod;
use Modules\Promotion\Enums\DiscountScope;
use Modules\Promotion\Events\CouponRedeemed;
use Modules\Promotion\Events\DiscountRedeemed;
use Modules\Promotion\Exceptions\DiscountLimitExceededException;
use Modules\Promotion\Services\DiscountUsageService;
use Modules\Purchase\Database\Factories\PurchaseFactory;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

function uCreate(array $overrides = []): \Modules\Promotion\Models\Discount
{
    return DiscountFactory::new()->create(array_merge([
        'application_method' => ApplicationMethod::Automatic,
        'status' => \Modules\Promotion\Enums\PromotionStatus::Active,
        'starts_at' => now()->subDay(),
        'ends_at' => null,
        'scope' => DiscountScope::All,
        'minimum_amount' => null,
        'usage_limit' => null,
        'usage_limit_per_customer' => null,
    ], $overrides));
}

beforeEach(function (): void {
    $this->usage = app(DiscountUsageService::class);
});

test('redeem persists a redemption row with exact discount_amount', function (): void {
    $d = uCreate();
    $user = ClientFactory::new()->create();
    $purchase = PurchaseFactory::new()->create();

    $redemption = $this->usage->redeem($d, $user, $purchase, 49.99, null, 100);
    $redemption->refresh();

    $this->assertDatabaseHas('discount_redemptions', [
        'id' => $redemption->id,
        'discount_id' => $d->id,
        'coupon_id' => null,
        'used_by_type' => $user->getMorphClass(),
        'used_by_id' => $user->getKey(),
        'discountable_type' => $purchase->getMorphClass(),
        'discountable_id' => $purchase->getKey(),
        'discount_amount' => 49.99,
    ]);
    expect($redemption->redeemed_at)->not->toBeNull();
});

test('redeem dispatches DiscountRedeemed (and CouponRedeemed when coupon provided) after commit', function (): void {
    Event::fake();

    $d = uCreate();
    $user = ClientFactory::new()->create();
    $purchase = PurchaseFactory::new()->create();

    $this->usage->redeem($d, $user, $purchase, 5, null, 100);

    Event::assertDispatched(DiscountRedeemed::class, 1);
    Event::assertNotDispatched(CouponRedeemed::class);

    Event::assertDispatched(DiscountRedeemed::class, function ($e) use ($d, $purchase) {
        return $e->redemption->discount_id === $d->id
            && $e->redemption->discountable_id === $purchase->id
            && \Modules\Promotion\Models\DiscountRedemption::query()->find($e->redemption->id) !== null;
    });

    Event::fake();
    $coupon = CouponFactory::new()->for($d, 'discount')->create();
    $this->usage->redeem($d, $user, $purchase, 5, $coupon, 100);
    Event::assertDispatched(DiscountRedeemed::class, 1);
    Event::assertDispatched(CouponRedeemed::class, 1);
});

test('redeem throws when global usage_limit already exhausted under lock', function (): void {
    $d = uCreate(['usage_limit' => 1]);
    DiscountRedemptionFactory::new()->for($d, 'discount')->create();
    $d->refresh();

    $user = ClientFactory::new()->create();
    $purchase = PurchaseFactory::new()->create();

    $this->expectException(DiscountLimitExceededException::class);
    $this->usage->redeem($d, $user, $purchase, 5, null, 9999);
});

test('redeem throws when per-customer limit exhausted', function (): void {
    $d = uCreate(['usage_limit_per_customer' => 1]);
    $user = ClientFactory::new()->create();
    DiscountRedemptionFactory::new()->for($d, 'discount')->create([
        'used_by_type' => $user->getMorphClass(),
        'used_by_id' => $user->getKey(),
    ]);
    $d->refresh();
    $purchase = PurchaseFactory::new()->create();

    $this->expectException(DiscountLimitExceededException::class);
    $this->usage->redeem($d, $user, $purchase, 5, null, 9999);
});
