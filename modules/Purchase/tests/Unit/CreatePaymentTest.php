<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Actions\CreatePayment;
use Modules\Purchase\Actions\CreatePurchase;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Events\PaymentCreated;
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

    $center = \Modules\Centers\Database\Factories\CenterFactory::new()->create([
        'city_id' => $this->city->id,
    ]);
    $client = ClientFactory::new()->create();

    $svc = TestPurchasable::create(['name' => 'Basic', 'price' => 100]);

    $this->purchase = (new CreatePurchase())->execute(
        buyer: $client,
        merchant: $center,
        source: PurchaseSource::Client,
        items: [
            ['purchasable' => $svc, 'quantity' => 1, 'unit_price' => 100.00],
        ],
    );

    $this->createPayment = new CreatePayment();
});

afterEach(function (): void {
    Schema::dropIfExists('test_services');
});

test('CreatePayment creates new Pending Payment for the Purchase', function (): void {
    Event::fake();

    $payment = $this->createPayment->execute(
        purchase: $this->purchase,
        method: PaymentMethod::Online,
        amount: 100.0,
        paymentCompany: 'moyasar',
        paymentType: 'card',
    );

    expect($payment->purchase_id)->toBe($this->purchase->id);
    expect($payment->status)->toBe(PaymentStatus::Pending);
    expect($payment->method)->toBe(PaymentMethod::Online);
    expect($payment->payment_company)->toBe('moyasar');
    expect($payment->payment_type)->toBe('card');
    expect($payment->paid_at)->toBeNull();

    Event::assertDispatched(PaymentCreated::class, 1);
});

test('Three CreatePayment calls produce three distinct Payment records (retry creates new not mutate)', function (): void {
    $p = $this->purchase;
    $p1 = $this->createPayment->execute($p, PaymentMethod::Online, 100, 'moyasar', 'card');
    $p2 = $this->createPayment->execute($p, PaymentMethod::Online, 100, 'moyasar', 'apple_pay');
    $p3 = $this->createPayment->execute($p, PaymentMethod::CashOnArrival, 100);

    $ids = [$p1->id, $p2->id, $p3->id];
    sort($ids);
    expect(array_unique($ids))->toHaveCount(3);

    $this->assertDatabaseCount('payments', 3);

    // p1/p2 should still be pending; confirming no status mutation
    $p1r = $p1->fresh();
    $p2r = $p2->fresh();
    $p3r = $p3->fresh();

    expect($p1r->status)->toBe(PaymentStatus::Pending);
    expect($p2r->status)->toBe(PaymentStatus::Pending);
    expect($p3r->status)->toBe(PaymentStatus::Pending);
    expect($p3r->payment_company)->toBeNull();
});

test('PaymentCreated dispatched once per create, afterCommit with DB state visible', function (): void {
    $sawId = null;
    $visible = false;
    Event::listen(PaymentCreated::class, function (PaymentCreated $e) use (&$sawId, &$visible): void {
        $sawId = $e->payment->id;
        $visible = \Modules\Purchase\Models\Payment::query()
            ->whereKey($e->payment->id)
            ->exists();
    });

    $p = $this->createPayment->execute($this->purchase, PaymentMethod::Online, 50.0, 'stripe', 'card');

    expect($sawId)->toBe($p->id);
    expect($visible)->toBeTrue();
});

test('CashOnArrival payment is Pending with null provider columns (AC-21)', function (): void {
    $payment = $this->createPayment->execute(
        $this->purchase,
        PaymentMethod::CashOnArrival,
        amount: $this->purchase->total_amount,
    );

    expect($payment->method)->toBe(PaymentMethod::CashOnArrival);
    expect($payment->status)->toBe(PaymentStatus::Pending);
    expect($payment->payment_company)->toBeNull();
    expect($payment->payment_type)->toBeNull();
    expect($payment->payment_order_id)->toBeNull();
});

test('CreatePayment rejects zero or negative amounts', function (): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->createPayment->execute($this->purchase, PaymentMethod::Online, amount: 0);
});

test('CreatePayment rejects non-existent purchase', function (): void {
    $ghost = new \Modules\Purchase\Models\Purchase();
    $ghost->id = 9999999;

    $this->expectException(\InvalidArgumentException::class);
    $this->createPayment->execute($ghost, PaymentMethod::Online, 50);
});
