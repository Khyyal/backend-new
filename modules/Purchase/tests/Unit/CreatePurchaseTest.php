<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Actions\CreatePurchase;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Events\PurchaseCreated;
use Modules\Purchase\Models\Merchant\Platform;
use Modules\Purchase\Tests\Support\TestPurchasable;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Schema::create('test_services', function (\Illuminate\Database\Schema\Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->decimal('price', 14, 2);
    });

    $this->city = \Modules\Support\Database\Factories\CityFactory::new()->create([
        'name' => ['en' => 'Test City', 'ar' => 'المدينة'],
    ]);

    $this->action = new CreatePurchase();
});

afterEach(function (): void {
    Schema::dropIfExists('test_services');
});

$makeCenterMerchant = function ($test) {
    return \Modules\Centers\Database\Factories\CenterFactory::new()->create([
        'city_id' => $test->city->id,
    ]);
};

$makePurchasable = static function (string $name = 'Facial', $price = 100.0): TestPurchasable {
    return TestPurchasable::create([
        'name' => $name,
        'price' => $price,
    ]);
};

test('CreatePurchase with Client buyer + Center merchant + 2 items persists correct buyer/merchant morphs', function () use ($makeCenterMerchant, $makePurchasable): void {
    Event::fake();

    $client = ClientFactory::new()->create();
    $center = $makeCenterMerchant($this);
    $svc1 = $makePurchasable('Swedish Massage', 250.00);
    $svc2 = $makePurchasable('Facial', 180.5);

    $purchase = $this->action->execute(
        buyer: $client,
        merchant: $center,
        source: PurchaseSource::Client,
        items: [
            [
                'purchasable' => $svc1,
                'quantity' => 1,
                'unit_price' => 250.00,
            ],
            [
                'purchasable' => $svc2,
                'quantity' => 2,
                'unit_price' => 180.5,
            ],
        ]
    );

    $purchase->load('buyer', 'merchant');

    expect($purchase->buyer_type)->toBe($client->getMorphClass());
    expect($purchase->buyer_id)->toBe($client->getKey());
    expect($purchase->buyer->getKey())->toBe($client->getKey());

    expect($purchase->merchant_type)->toBe($center->getMorphClass());
    expect($purchase->merchant_id)->toBe($center->getKey());
    expect($purchase->merchant->getKey())->toBe($center->getKey());

    expect($purchase->source)->toBe(PurchaseSource::Client);
    expect($purchase->items)->toHaveCount(2);

    Event::assertDispatched(PurchaseCreated::class, 1);
});

test('CreatePurchase with Center buyer + Platform merchant (center membership purchase)', function () use ($makeCenterMerchant, $makePurchasable): void {
    Event::fake();

    $center = $makeCenterMerchant($this);
    $platform = new Platform();
    $plan = $makePurchasable('Premium Membership', 1200.0);

    $purchase = $this->action->execute(
        buyer: $center,
        merchant: $platform,
        source: PurchaseSource::Center,
        items: [
            [
                'purchasable' => $plan,
                'name' => 'Annual Premium Plan',
                'quantity' => 1,
                'unit_price' => 1200.0,
                'tax_amount' => 180.0,
            ],
        ],
        metadata: ['plan_code' => 'premium_annual_v1']
    );

    expect($purchase->buyer_type)->toBe($center->getMorphClass());
    expect($purchase->merchant_type)->toBe('platform');
    expect($purchase->source)->toBe(PurchaseSource::Center);
    expect($purchase->metadata)->toBe(['plan_code' => 'premium_annual_v1']);
    expect($purchase->items[0]->name)->toBe('Annual Premium Plan');

    Event::assertDispatched(PurchaseCreated::class, 1);
});

test('Purchase totals equal sum of item totals (subtotal/discount/tax/total)', function () use ($makeCenterMerchant, $makePurchasable): void {
    $client = ClientFactory::new()->create();
    $center = $makeCenterMerchant($this);
    $svc1 = $makePurchasable('Svc1', 100);
    $svc2 = $makePurchasable('Svc2', 200);

    $purchase = $this->action->execute($client, $center, PurchaseSource::Client, [
        [
            'purchasable' => $svc1,
            'quantity' => 2,
            'unit_price' => 100.00,
            'discount_amount' => 20.00,
            'tax_amount' => 9.0,
        ],
        [
            'purchasable' => $svc2,
            'quantity' => 1,
            'unit_price' => 200.00,
            'discount_amount' => 0,
            'tax_amount' => 30.0,
        ],
    ]);

    [$i1, $i2] = [$purchase->items[0], $purchase->items[1]];

    $this->assertEqualsWithDelta(200.0, $i1->subtotal, 0.001, 'item1 subtotal');
    $this->assertEqualsWithDelta(20.0, $i1->discount_amount, 0.001, 'item1 discount');
    $this->assertEqualsWithDelta(9.0, $i1->tax_amount, 0.001, 'item1 tax');
    $this->assertEqualsWithDelta(189.0, $i1->total_amount, 0.001, 'item1 total');

    $this->assertEqualsWithDelta(200.0, $i2->subtotal, 0.001, 'item2 subtotal');
    $this->assertEqualsWithDelta(0.0, $i2->discount_amount, 0.001, 'item2 discount');
    $this->assertEqualsWithDelta(30.0, $i2->tax_amount, 0.001, 'item2 tax');
    $this->assertEqualsWithDelta(230.0, $i2->total_amount, 0.001, 'item2 total');

    $this->assertEqualsWithDelta(400.0, $purchase->subtotal, 0.001, 'purchase subtotal');
    $this->assertEqualsWithDelta(20.0, $purchase->discount_amount, 0.001, 'purchase discount');
    $this->assertEqualsWithDelta(39.0, $purchase->tax_amount, 0.001, 'purchase tax');
    $this->assertEqualsWithDelta(419.0, $purchase->total_amount, 0.001, 'purchase total');
});

test('PurchaseItem total_amount invariant = (unit_price*qty) - discount + tax', function () use ($makeCenterMerchant, $makePurchasable): void {
    $client = ClientFactory::new()->create();
    $center = $makeCenterMerchant($this);
    $svc = $makePurchasable('Haircut', 80.0);

    $purchase = $this->action->execute($client, $center, PurchaseSource::Client, [
        [
            'purchasable' => $svc,
            'quantity' => 3,
            'unit_price' => 79.99,
            'discount_amount' => 20.00,
            'tax_amount' => 12.50,
        ],
    ]);

    $item = $purchase->items[0];
    $expected = (79.99 * 3) - 20.00 + 12.50;
    $this->assertEqualsWithDelta($expected, $item->total_amount, 0.001);
    expect($item->quantity)->toBe(3);
});

test('CreatePurchase with 3 items creates exactly 3 purchase_items rows', function () use ($makeCenterMerchant, $makePurchasable): void {
    $client = ClientFactory::new()->create();
    $center = $makeCenterMerchant($this);

    $items = [];
    for ($i = 1; $i <= 3; $i++) {
        $svc = $makePurchasable("Service $i", 50.0 * $i);
        $items[] = [
            'purchasable' => $svc,
            'quantity' => 1,
            'unit_price' => 50.0 * $i,
        ];
    }

    $purchase = $this->action->execute($client, $center, PurchaseSource::Client, $items);

    $this->assertDatabaseCount('purchase_items', 3);
    $this->assertSame(3, $purchase->items->count());

    foreach ($purchase->items as $item) {
        expect($item->purchase_id)->toBe($purchase->id);
    }
});

test('Purchase metadata round-trips as array when stored via CreatePurchase', function () use ($makeCenterMerchant, $makePurchasable): void {
    $client = ClientFactory::new()->create();
    $center = $makeCenterMerchant($this);
    $svc = $makePurchasable('x', 10.0);

    $meta = ['promo' => 'NY2026', 'ref' => 999, 'tags' => ['a', 'b']];

    $purchase = $this->action->execute($client, $center, PurchaseSource::System, [
        ['purchasable' => $svc, 'quantity' => 1, 'unit_price' => 10.0],
    ], $meta);

    $reloaded = \Modules\Purchase\Models\Purchase::findOrFail($purchase->id);

    expect($reloaded->metadata)->toBe($meta);
    expect($reloaded->metadata['tags'][0])->toBe('a');
});

test('PurchaseCreated event fires only after DB commit (state is queryable inside listener)', function () use ($makeCenterMerchant, $makePurchasable): void {
    $client = ClientFactory::new()->create();
    $center = $makeCenterMerchant($this);
    $svc = $makePurchasable('Commit test svc', 55.0);

    $purchaseIdFromEvent = null;
    $rowVisibleInEvent = false;

    Event::listen(PurchaseCreated::class, function (PurchaseCreated $e) use (
        &$purchaseIdFromEvent,
        &$rowVisibleInEvent,
    ): void {
        $purchaseIdFromEvent = $e->purchase->id;
        $rowVisibleInEvent = \Modules\Purchase\Models\Purchase::query()
            ->whereKey($e->purchase->id)
            ->exists();
    });

    $purchase = $this->action->execute($client, $center, PurchaseSource::Client, [
        ['purchasable' => $svc, 'quantity' => 1, 'unit_price' => 55.0],
    ]);

    expect($purchaseIdFromEvent)->toBe($purchase->id);
    expect($rowVisibleInEvent)->toBeTrue();
});

test('CreatePurchase rejects empty items list', function () use ($makeCenterMerchant): void {
    $client = ClientFactory::new()->create();
    $center = $makeCenterMerchant($this);

    $this->expectException(\InvalidArgumentException::class);
    $this->action->execute($client, $center, PurchaseSource::Client, items: []);
});

test('CreatePurchase rejects items without purchasable', function () use ($makeCenterMerchant): void {
    $client = ClientFactory::new()->create();
    $center = $makeCenterMerchant($this);

    $this->expectException(\InvalidArgumentException::class);
    $this->action->execute($client, $center, PurchaseSource::Client, [
        ['quantity' => 1, 'unit_price' => 10],
    ]);
});

test('CreatePurchase rejects zero quantity', function () use ($makeCenterMerchant, $makePurchasable): void {
    $client = ClientFactory::new()->create();
    $center = $makeCenterMerchant($this);
    $svc = $makePurchasable('x', 10);

    $this->expectException(\InvalidArgumentException::class);
    $this->action->execute($client, $center, PurchaseSource::Client, [
        ['purchasable' => $svc, 'quantity' => 0, 'unit_price' => 10],
    ]);
});

test('CreatePurchase rejects negative unit_price', function () use ($makeCenterMerchant, $makePurchasable): void {
    $client = ClientFactory::new()->create();
    $center = $makeCenterMerchant($this);
    $svc = $makePurchasable('x', 10);

    $this->expectException(\InvalidArgumentException::class);
    $this->action->execute($client, $center, PurchaseSource::Client, [
        ['purchasable' => $svc, 'unit_price' => -1.0],
    ]);
});
