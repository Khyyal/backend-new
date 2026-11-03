<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Promotions\Database\Factories\CouponFactory;
use Modules\Promotions\Models\PromotionRedemption;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('rebuilds drifted usage counters from the applied redemptions', function (): void {
    $coupon = CouponFactory::new()->create(['used_count' => 5]);
    $client = ClientFactory::new()->create();

    foreach (['applied', 'released'] as $status) {
        PromotionRedemption::query()->create([
            'promotion_id' => $coupon->promotion_id,
            'coupon_id' => $coupon->id,
            'purchase_id' => PurchaseFactory::new()->create()->id,
            'client_id' => $client->id,
            'discount_amount' => 100,
            'status' => $status,
        ]);
    }

    $this->artisan('promotions:reconcile')->assertSuccessful();

    expect($coupon->fresh()->used_count)->toBe(1)
        ->and($coupon->promotion->fresh()->usage_count)->toBe(1)
        ->and(DB::table('coupon_client_usages')->where('coupon_id', $coupon->id)->value('used_count'))->toBe(1);
});
