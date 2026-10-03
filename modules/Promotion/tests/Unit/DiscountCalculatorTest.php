<?php

use Modules\Promotion\Database\Factories\DiscountFactory;
use Modules\Promotion\Enums\ApplicationMethod;
use Modules\Promotion\Enums\DiscountScope;
use Modules\Promotion\Enums\DiscountType;
use Modules\Promotion\Services\DiscountCalculator;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

function cCreate(array $overrides = []): \Modules\Promotion\Models\Discount
{
    return DiscountFactory::new()->create(array_merge([
        'application_method' => ApplicationMethod::Automatic,
        'status' => \Modules\Promotion\Enums\PromotionStatus::Active,
        'scope' => DiscountScope::All,
        'minimum_amount' => null,
        'maximum_discount' => null,
    ], $overrides));
}

beforeEach(function (): void {
    $this->calc = app(DiscountCalculator::class);
});

test('fixed discount returns full value when subtotal is large enough', function (): void {
    $d = cCreate(['type' => DiscountType::Fixed, 'value' => 50]);
    $result = $this->calc->calculate($d, [], 200);
    expect($result)->toBe(50.0);
});

test('fixed discount cannot exceed subtotal', function (): void {
    $d = cCreate(['type' => DiscountType::Fixed, 'value' => 50]);
    $result = $this->calc->calculate($d, [], 30);
    expect($result)->toBe(30.0);
});

test('percentage discount returns proportional amount', function (): void {
    $d = cCreate(['type' => DiscountType::Percentage, 'value' => 10]);
    $result = $this->calc->calculate($d, [], 200);
    expect($result)->toBe(20.0);
});

test('percentage with maximum_discount caps the result', function (): void {
    $d = cCreate([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'maximum_discount' => 5,
    ]);
    $result = $this->calc->calculate($d, [], 200);
    expect($result)->toBe(5.0);
});

test('percentage does not exceed subtotal at tiny values', function (): void {
    $d = cCreate(['type' => DiscountType::Percentage, 'value' => 200]);
    $result = $this->calc->calculate($d, [], 0.01);
    expect($result)->toBe(0.01);
});

test('every result has at most two decimal digits', function (): void {
    $d1 = cCreate(['type' => DiscountType::Percentage, 'value' => 33.3333]);
    $r1 = $this->calc->calculate($d1, [], 100);
    $r2 = $this->calc->calculate($d1, [], 0.01);

    $this->assertMatchesRegularExpression('/^\d+(\.\d{1,2})?$/', (string) $r1);
    $this->assertMatchesRegularExpression('/^\d+(\.\d{1,2})?$/', (string) $r2);
});
