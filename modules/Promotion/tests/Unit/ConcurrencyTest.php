<?php

use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Promotion\Database\Factories\DiscountFactory;
use Modules\Promotion\Enums\ApplicationMethod;
use Modules\Promotion\Enums\DiscountScope;
use Modules\Promotion\Exceptions\DiscountLimitExceededException;
use Modules\Promotion\Services\DiscountUsageService;
use Modules\Purchase\Database\Factories\PurchaseFactory;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

test('two sequential redeems for last slot: exactly one succeeds and second throws', function (): void {
    $d = DiscountFactory::new()->create([
        'application_method' => ApplicationMethod::Automatic,
        'status' => \Modules\Promotion\Enums\PromotionStatus::Active,
        'starts_at' => now()->subDay(),
        'ends_at' => null,
        'scope' => DiscountScope::All,
        'minimum_amount' => null,
        'usage_limit' => 1,
        'usage_limit_per_customer' => null,
    ]);
    $user = ClientFactory::new()->create();
    $purchase = PurchaseFactory::new()->create();
    $usage = app(DiscountUsageService::class);

    $succeeded = 0;
    $threw = 0;

    try {
        $lockDiscount = \Modules\Promotion\Models\Discount::query()
            ->whereKey($d->id)
            ->lockForUpdate()
            ->firstOrFail();
        $usage->redeem($lockDiscount, $user, $purchase, 10, null, 9999);
        $succeeded++;
    } catch (DiscountLimitExceededException $e) {
        $threw++;
    }

    try {
        $lockDiscount = \Modules\Promotion\Models\Discount::query()
            ->whereKey($d->id)
            ->lockForUpdate()
            ->firstOrFail();
        $usage->redeem($lockDiscount, $user, $purchase, 10, null, 9999);
        $succeeded++;
    } catch (DiscountLimitExceededException $e) {
        $threw++;
    }

    expect($succeeded)->toBe(1);
    expect($threw)->toBe(1);
    $this->assertDatabaseCount('discount_redemptions', 1);
});
