<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Promotion\Database\Factories\DiscountFactory;
use Modules\Promotion\Database\Factories\DiscountRedemptionFactory;
use Modules\Promotion\Enums\DiscountScope;
use Modules\Promotion\Enums\PromotionStatus;
use Modules\Promotion\Models\Discount;
use Modules\Promotion\Services\DiscountEligibilityService;
use Modules\Services\Database\Factories\ServiceFactory;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->elig = app(DiscountEligibilityService::class);
});

function baseCreate(array $overrides = []): Discount
{
    return DiscountFactory::new()->create(array_merge([
        'scope' => DiscountScope::All,
        'minimum_amount' => null,
        'usage_limit' => null,
        'usage_limit_per_customer' => null,
        'ends_at' => null,
    ], $overrides));
}

test('inactive discount is never eligible', function (): void {
    $d = baseCreate(['status' => PromotionStatus::InActive]);
    expect($this->elig->isEligible($d))->toBeFalse();
});

test('discount with future starts_at is ineligible; past ends_at is ineligible', function (): void {
    $future = baseCreate(['starts_at' => now()->addDay()]);
    expect($this->elig->isEligible($future))->toBeFalse();

    $past = baseCreate([
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->subDay(),
    ]);
    expect($this->elig->isEligible($past))->toBeFalse();

    $open = baseCreate([
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);
    expect($this->elig->isEligible($open))->toBeTrue();

    $noEnd = baseCreate([
        'starts_at' => now()->subDay(),
        'ends_at' => null,
    ]);
    expect($this->elig->isEligible($noEnd))->toBeTrue();
});

test('minimum_amount boundary is inclusive', function (): void {
    $d = baseCreate(['minimum_amount' => 100]);
    expect($this->elig->isEligible($d, null, 99.99))->toBeFalse();
    expect($this->elig->isEligible($d, null, 100))->toBeTrue();
    expect($this->elig->isEligible($d, null, 100.01))->toBeTrue();
});

test('usage_limit prevents eligibility when reached', function (): void {
    $d = baseCreate(['usage_limit' => 1]);
    expect($this->elig->isEligible($d))->toBeTrue();

    DiscountRedemptionFactory::new()->for($d, 'discount')->create();
    $d->refresh();
    expect($this->elig->isEligible($d))->toBeFalse();
});

test('usage_limit_per_customer is per-user', function (): void {
    $d = baseCreate(['usage_limit_per_customer' => 1]);
    $uA = ClientFactory::new()->create();
    $uB = ClientFactory::new()->create();

    DiscountRedemptionFactory::new()->for($d, 'discount')->create([
        'used_by_type' => $uA->getMorphClass(),
        'used_by_id' => $uA->getKey(),
    ]);
    $d->refresh();

    expect($this->elig->isEligible($d, $uA))->toBeFalse();
    expect($this->elig->isEligible($d, $uB))->toBeTrue();
});

test('specific_items scope requires at least one attached service', function (): void {
    $d = baseCreate(['scope' => DiscountScope::SpecificItems]);
    $in = ServiceFactory::new()->create(['center_id' => $d->center_id]);
    $out = ServiceFactory::new()->create(['center_id' => $d->center_id]);
    $d->services()->attach($in);
    $d->refresh();

    expect($this->elig->isEligible($d, null, 100, []))->toBeFalse();
    expect($this->elig->isEligible($d, null, 100, [$out]))->toBeFalse();
    expect($this->elig->isEligible($d, null, 100, [$in]))->toBeTrue();
    expect($this->elig->isEligible($d, null, 100, [$out, $in]))->toBeTrue();
    expect($this->elig->isEligible($d, null, 100, [$in->id]))->toBeTrue();
});
