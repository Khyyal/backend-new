<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Promotion\Database\Factories\DiscountFactory;
use Modules\Promotion\Database\Factories\DiscountRedemptionFactory;
use Modules\Promotion\Enums\ApplicationMethod;
use Modules\Promotion\Enums\DiscountScope;
use Modules\Promotion\Enums\PromotionStatus;
use Modules\Promotion\Models\Discount;
use Modules\Promotion\Services\DiscountEligibilityService;
use Modules\Promotion\Services\DiscountResolver;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function rCreate(array $overrides = []): Discount
{
    return DiscountFactory::new()->create(array_merge([
        'center_id' => test()->center->id,
        'application_method' => ApplicationMethod::Automatic,
        'status' => PromotionStatus::Active,
        'starts_at' => now()->subDay(),
        'ends_at' => null,
        'scope' => DiscountScope::All,
        'minimum_amount' => null,
        'usage_limit' => null,
        'usage_limit_per_customer' => null,
    ], $overrides));
}

beforeEach(function (): void {
    $this->resolver = app(DiscountResolver::class);
    $this->center = CenterFactory::new()->create();
});

test('resolveAutomatic returns only automatic active date-valid discounts', function (): void {
    $auto = rCreate();
    $couponOnly = rCreate(['application_method' => ApplicationMethod::Coupon]);
    $inactive = rCreate(['status' => PromotionStatus::InActive]);
    $future = rCreate(['starts_at' => now()->addDay()]);

    $ids = collect($this->resolver->resolveAutomatic($this->center))->pluck('id');

    expect($ids)->toContain($auto->id);
    expect($ids)->not->toContain($couponOnly->id);
    expect($ids)->not->toContain($inactive->id);
    expect($ids)->not->toContain($future->id);
});

test('resolveAutomatic only returns discounts of the given center', function (): void {
    $c1 = CenterFactory::new()->create();
    $c2 = CenterFactory::new()->create();
    $d1 = rCreate(['center_id' => $c1->id]);
    $d2 = rCreate(['center_id' => $c2->id]);

    $ids1 = collect($this->resolver->resolveAutomatic($c1))->pluck('id');
    $ids2 = collect($this->resolver->resolveAutomatic($c2))->pluck('id');

    expect($ids1)->toContain($d1->id);
    expect($ids1)->not->toContain($d2->id);
    expect($ids2)->toContain($d2->id);
    expect($ids2)->not->toContain($d1->id);
});

test('every returned discount passes independent eligibility check', function (): void {
    $elig = app(DiscountEligibilityService::class);
    $client = ClientFactory::new()->create();

    rCreate(['minimum_amount' => 500]);
    $exhausted = rCreate(['usage_limit' => 1]);
    DiscountRedemptionFactory::new()->for($exhausted, 'discount')->create([
        'used_by_type' => $client->getMorphClass(),
        'used_by_id' => $client->getKey(),
        'discountable_type' => 'purchase',
        'discountable_id' => 1,
        'discount_amount' => 5,
    ]);
    rCreate(['minimum_amount' => 5]);

    $results = $this->resolver->resolveAutomatic($this->center, $client, 100);
    foreach ($results as $disc) {
        expect($elig->isEligible($disc, $client, 100))->toBeTrue();
    }
    expect(count($results))->toBe(1);
});

test('results are ordered by id ascending', function (): void {
    rCreate();
    rCreate();
    rCreate();

    $ids = collect($this->resolver->resolveAutomatic($this->center))->pluck('id')->all();
    $sorted = $ids;
    sort($sorted, SORT_NUMERIC);
    expect($ids)->toBe($sorted);
});
