<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Promotions\Data\DiscountableLine;
use Modules\Promotions\Database\Factories\PromotionFactory;
use Modules\Promotions\Enums\TargetMode;
use Modules\Promotions\Enums\TargetType;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Services\DiscountEngine;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->center = CenterFactory::new()->create();
    $this->engine = new DiscountEngine;

    $this->line = fn (int $index, int $unitPrice, int $quantity = 1, ?int $serviceId = 1): DiscountableLine => new DiscountableLine($index, $this->center->id, $serviceId, $unitPrice, $quantity);
    $this->apply = fn (array $lines, Promotion ...$promotions) => $this->engine->apply($lines, Collection::make($promotions)->each->load(['targets']));
});

test('a percentage in basis points rounds each line down', function (): void {
    $promotion = PromotionFactory::new()->active()->percentage(1250)->create();

    $result = ($this->apply)([($this->line)(0, 999)], $promotion);

    expect($result->lineDiscounts)->toBe([0 => 124]);
});

test('a fixed amount is taken per unit and never exceeds the line', function (): void {
    $promotion = PromotionFactory::new()->active()->fixedAmount(300)->create();

    $result = ($this->apply)([($this->line)(0, 1000, 2), ($this->line)(1, 200)], $promotion);

    expect($result->lineDiscounts)->toBe([0 => 600, 1 => 200]);
});

test('a fixed price lowers a unit to the price and never raises it', function (): void {
    $promotion = PromotionFactory::new()->active()->fixedPrice(700)->create();

    $result = ($this->apply)([($this->line)(0, 1000, 3), ($this->line)(1, 500)], $promotion);

    expect($result->lineDiscounts)->toBe([0 => 900, 1 => 0]);
});

test('each line takes only its best item offer', function (): void {
    $small = PromotionFactory::new()->active()->percentage(1000)->create();
    $large = PromotionFactory::new()->active()->fixedAmount(500)->create();

    $result = ($this->apply)([($this->line)(0, 2000)], $small, $large);

    expect($result->lineDiscounts)->toBe([0 => 500])
        ->and($result->adjustments)->toHaveCount(1)
        ->and($result->adjustments[0]->promotionId)->toBe($large->id);
});

test('equal offers are broken by priority, then by the oldest promotion', function (): void {
    $older = PromotionFactory::new()->active()->fixedAmount(100)->create();
    $newer = PromotionFactory::new()->active()->fixedAmount(100)->create();
    $prioritised = PromotionFactory::new()->active()->fixedAmount(100)->create(['priority' => 5]);

    expect(($this->apply)([($this->line)(0, 1000)], $older, $newer)->adjustments[0]->promotionId)->toBe($older->id)
        ->and(($this->apply)([($this->line)(0, 1000)], $older, $prioritised)->adjustments[0]->promotionId)->toBe($prioritised->id);
});

test('service targets include and exclude lines', function (): void {
    $included = PromotionFactory::new()->active()->percentage(5000)->create();
    $included->targets()->create(['target_type' => TargetType::Service, 'target_id' => 7, 'mode' => TargetMode::Include]);
    $excluded = PromotionFactory::new()->active()->percentage(5000)->create();
    $excluded->targets()->create(['target_type' => TargetType::Service, 'target_id' => 7, 'mode' => TargetMode::Exclude]);

    $lines = [($this->line)(0, 1000, serviceId: 7), ($this->line)(1, 1000, serviceId: 8)];

    expect(($this->apply)($lines, $included)->lineDiscounts)->toBe([0 => 500, 1 => 0])
        ->and(($this->apply)($lines, $excluded)->lineDiscounts)->toBe([0 => 0, 1 => 500]);
});

test('a promotion of another center does not apply', function (): void {
    $promotion = PromotionFactory::new()->active()->forCenter(CenterFactory::new()->create())->create();

    expect(($this->apply)([($this->line)(0, 1000)], $promotion)->totalDiscount())->toBe(0);
});

test('the cap limits a promotion across all its lines', function (): void {
    $promotion = PromotionFactory::new()->active()->percentage(5000)->create(['max_discount_amount' => 700]);

    $result = ($this->apply)([($this->line)(0, 1000), ($this->line)(1, 1000)], $promotion);

    expect($result->lineDiscounts)->toBe([0 => 500, 1 => 200]);
});

test('the minimum spend is judged on the matching lines only', function (): void {
    $promotion = PromotionFactory::new()->active()->percentage(1000)->create(['min_subtotal_amount' => 1500]);
    $promotion->targets()->create(['target_type' => TargetType::Service, 'target_id' => 7, 'mode' => TargetMode::Include]);

    $result = ($this->apply)([($this->line)(0, 1000, serviceId: 7), ($this->line)(1, 5000, serviceId: 8)], $promotion);

    expect($result->totalDiscount())->toBe(0)
        ->and($this->engine->eligibility($promotion->load('targets'), [($this->line)(0, 1000, serviceId: 7)]))->toBe('min_subtotal');
});

test('an order offer applies on what item offers leave and is spread exactly over the lines', function (): void {
    $item = PromotionFactory::new()->active()->fixedAmount(100)->create();
    $order = PromotionFactory::new()->active()->orderScope()->fixedAmount(1000)->create();

    $result = ($this->apply)([($this->line)(0, 1000), ($this->line)(1, 2000)], $item, $order);

    expect($result->totalDiscount())->toBe(1200)
        ->and($result->lineDiscounts)->toBe([0 => 100 + 321, 1 => 100 + 679])
        ->and($result->adjustments)->toHaveCount(3);
});

test('an exclusive item offer blocks a stackable order offer', function (): void {
    $item = PromotionFactory::new()->active()->exclusive()->fixedAmount(100)->create();
    $order = PromotionFactory::new()->active()->orderScope()->fixedAmount(1000)->create();

    expect(($this->apply)([($this->line)(0, 3000)], $item, $order)->totalDiscount())->toBe(100);
});

test('an exclusive order offer replaces item offers only when it gives more', function (): void {
    $item = PromotionFactory::new()->active()->fixedAmount(500)->create();
    $better = PromotionFactory::new()->active()->orderScope()->exclusive()->fixedAmount(800)->create();
    $worse = PromotionFactory::new()->active()->orderScope()->exclusive()->fixedAmount(400)->create();

    $replaced = ($this->apply)([($this->line)(0, 3000)], $item, $better);
    $kept = ($this->apply)([($this->line)(0, 3000)], $item, $worse);

    expect($replaced->totalDiscount())->toBe(800)
        ->and($replaced->adjustments)->toHaveCount(1)
        ->and($replaced->adjustments[0]->promotionId)->toBe($better->id)
        ->and($kept->totalDiscount())->toBe(500);
});

test('the center bears its share of a shared discount', function (): void {
    $promotion = PromotionFactory::new()->active()->fixedAmount(1000)->create(['funded_by' => 'shared', 'center_share_bps' => 2500]);

    $adjustment = ($this->apply)([($this->line)(0, 5000)], $promotion)->adjustments[0];

    expect($adjustment->centerShareAmount)->toBe(250);
});
