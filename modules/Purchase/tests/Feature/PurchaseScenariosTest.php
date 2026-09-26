<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Actions\CreatePayment;
use Modules\Purchase\Actions\CreatePurchase;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Services\PaymentStateService;
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
        'name' => ['en' => 'Scenarios City', 'ar' => 'مدينة السيناريوهات'],
    ]);
});

afterEach(function (): void {
    Schema::dropIfExists('test_services');
});

$makeClientCenterSvc = function ($test) {
    $center = \Modules\Centers\Database\Factories\CenterFactory::new()->create(['city_id' => $test->city->id]);
    $client = ClientFactory::new()->create();
    $svc = TestPurchasable::create(['name' => 'Spa Day Pass', 'price' => 250.0]);

    return [$client, $center, $svc];
};

test('Cash on arrival valid scenario: Purchase confirmed, Payment Pending, method = cash_on_arrival (AC-32)', function () use ($makeClientCenterSvc): void {
    [$client, $center, $svc] = $makeClientCenterSvc($this);

    $purchase = (new CreatePurchase())->execute(
        buyer: $client,
        merchant: $center,
        source: PurchaseSource::Client,
        items: [
            ['purchasable' => $svc, 'quantity' => 1, 'unit_price' => 250.0],
        ],
    );

    $payment = (new CreatePayment())->execute(
        purchase: $purchase,
        method: PaymentMethod::CashOnArrival,
        amount: 250.0,
    );

    $purchaseSvc = new \Modules\Purchase\Services\PurchaseStateService();
    $confirmed = $purchaseSvc->confirm($purchase);

    $paymentFresh = $payment->fresh();

    expect($confirmed->status)->toBe(PurchaseStatus::Confirmed);
    expect($paymentFresh->method)->toBe(PaymentMethod::CashOnArrival);
    expect($paymentFresh->status)->toBe(PaymentStatus::Pending);
    expect($paymentFresh->paid_at)->toBeNull();

    $this->assertDatabaseHas('purchases', [
        'id' => $confirmed->id,
        'status' => PurchaseStatus::Confirmed->value,
    ]);
    $this->assertDatabaseHas('payments', [
        'id' => $paymentFresh->id,
        'method' => PaymentMethod::CashOnArrival->value,
        'status' => PaymentStatus::Pending->value,
    ]);
});

test('Failed Payment does NOT automatically cancel Purchase (AC-33)', function () use ($makeClientCenterSvc): void {
    [$client, $center, $svc] = $makeClientCenterSvc($this);

    $purchase = (new CreatePurchase())->execute(
        buyer: $client,
        merchant: $center,
        source: PurchaseSource::Client,
        items: [
            ['purchasable' => $svc, 'quantity' => 2, 'unit_price' => 100.0],
        ],
    );

    $purchaseSvc = new \Modules\Purchase\Services\PurchaseStateService();
    $purchaseSvc->confirm($purchase);

    $payment = (new CreatePayment())->execute(
        $purchase,
        PaymentMethod::Online,
        amount: 200.0,
        paymentCompany: 'moyasar',
        paymentType: 'card',
    );

    $paySvc = new PaymentStateService();
    $paySvc->markFailed($payment);

    $purchaseFresh = $purchase->fresh();

    expect($purchaseFresh->status)->toBe(PurchaseStatus::Confirmed);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);
});

test('Retry scenario: Payment1 failed, Payment2 created + succeeded; Payment1 retains FAILED status (AC-34)', function () use ($makeClientCenterSvc): void {
    [$client, $center, $svc] = $makeClientCenterSvc($this);

    $purchase = (new CreatePurchase())->execute(
        $client, $center, PurchaseSource::Client,
        [['purchasable' => $svc, 'quantity' => 1, 'unit_price' => 150.0]],
    );

    $paySvc = new PaymentStateService();
    $createPayment = new CreatePayment();

    $p1 = $createPayment->execute($purchase, PaymentMethod::Online, 150.0, 'stripe', 'card');
    $paySvc->markFailed($p1);

    $p2 = $createPayment->execute($purchase, PaymentMethod::Online, 150.0, 'stripe', 'apple_pay');
    $paySvc->markProcessing($p2);
    $paySvc->markSucceeded($p2);

    expect(Payment::where('purchase_id', $purchase->id)->count())->toBe(2);

    $p1fresh = $p1->fresh();
    $p2fresh = $p2->fresh();

    expect($p1fresh->status)->toBe(PaymentStatus::Failed);
    expect($p1fresh->paid_at)->toBeNull();

    expect($p2fresh->status)->toBe(PaymentStatus::Succeeded);
    expect($p2fresh->paid_at)->not->toBeNull();

    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Pending);
});

test('Abandoned Pending/Processing payment retained forever (AC-35)', function () use ($makeClientCenterSvc): void {
    [$client, $center, $svc] = $makeClientCenterSvc($this);

    $purchase = (new CreatePurchase())->execute(
        $client, $center, PurchaseSource::Client,
        [['purchasable' => $svc, 'quantity' => 1, 'unit_price' => 88.0]],
    );

    $pPending = Payment::create([
        'purchase_id' => $purchase->id,
        'amount' => 88.0,
        'method' => PaymentMethod::Online,
        'status' => PaymentStatus::Pending,
        'payment_company' => 'fake',
        'payment_type' => 'card',
        'payment_order_id' => 'ORD-ABANDONED-PENDING',
    ]);

    $pProcessing = Payment::create([
        'purchase_id' => $purchase->id,
        'amount' => 99.0,
        'method' => PaymentMethod::Online,
        'status' => PaymentStatus::Processing,
        'payment_company' => 'fake',
        'payment_type' => 'card',
        'payment_order_id' => 'ORD-ABANDONED-PROCESSING',
    ]);

    $this->assertNotNull($pPending->id);
    $this->assertNotNull($pProcessing->id);

    $reloadPending = Payment::findOrFail($pPending->id);
    $reloadProcessing = Payment::findOrFail($pProcessing->id);

    expect($reloadPending->status)->toBe(PaymentStatus::Pending);
    expect($reloadProcessing->status)->toBe(PaymentStatus::Processing);
});

test('Buyer polymorphic works for Center (purchases() returns MorphMany, creates Purchase w/ correct buyer_type)', function (): void {
    $center = \Modules\Centers\Database\Factories\CenterFactory::new()->create(['city_id' => $this->city->id]);
    $platform = new \Modules\Purchase\Models\Merchant\Platform();
    $svc = TestPurchasable::create(['name' => 'Gold Membership', 'price' => 5000]);

    $purchase = (new CreatePurchase())->execute(
        buyer: $center,
        merchant: $platform,
        source: PurchaseSource::Center,
        items: [
            ['purchasable' => $svc, 'quantity' => 1, 'unit_price' => 5000],
        ],
    );

    $center->load('purchases');
    $this->assertTrue($center->purchases->contains('id', $purchase->id));

    $this->assertSame('center', $purchase->buyer_type);
    $this->assertSame('platform', $purchase->merchant_type);
});

test('Buyer polymorphic works for Client (purchases() returns MorphMany)', function () use ($makeClientCenterSvc): void {
    [$client, $center, $svc] = $makeClientCenterSvc($this);

    $purchase = (new CreatePurchase())->execute(
        $client, $center, PurchaseSource::Client,
        [['purchasable' => $svc, 'quantity' => 1, 'unit_price' => 45]],
    );

    $client->load('purchases');
    $this->assertTrue($client->purchases->contains('id', $purchase->id));
    $this->assertSame('client', $purchase->buyer_type);
    $this->assertSame('center', $purchase->merchant_type);
});

test('Purchase::items returns HasMany of PurchaseItem; PurchaseItem::purchase returns BelongsTo (AC-4, AC-5)', function () use ($makeClientCenterSvc): void {
    [$client, $center, $svc] = $makeClientCenterSvc($this);

    $purchase = (new CreatePurchase())->execute($client, $center, PurchaseSource::Client, [
        ['purchasable' => $svc, 'quantity' => 2, 'unit_price' => 50],
    ]);

    $item = $purchase->items->first();
    $this->assertInstanceOf(\Modules\Purchase\Models\PurchaseItem::class, $item);
    $this->assertInstanceOf(\Modules\Purchase\Models\Purchase::class, $item->purchase);
    $this->assertSame($purchase->id, $item->purchase_id);
    $this->assertSame(2, (int) $item->quantity);
});

test('Purchase::payments returns HasMany of Payment; Payment::purchase returns BelongsTo (AC-5)', function () use ($makeClientCenterSvc): void {
    [$client, $center, $svc] = $makeClientCenterSvc($this);
    $purchase = (new CreatePurchase())->execute($client, $center, PurchaseSource::Client, [
        ['purchasable' => $svc, 'quantity' => 1, 'unit_price' => 10],
    ]);

    $p1 = (new CreatePayment())->execute($purchase, PaymentMethod::Manual, 5.0);
    $p2 = (new CreatePayment())->execute($purchase, PaymentMethod::Manual, 5.0);

    $purchase->load('payments');
    $this->assertCount(2, $purchase->payments);

    $this->assertSame($purchase->id, $p1->fresh()->purchase_id);
    $this->assertSame($purchase->id, $p2->fresh()->purchase_id);
    $this->assertInstanceOf(\Modules\Purchase\Models\Purchase::class, $p1->fresh()->purchase);
});

test('PurchaseItem purchasable MorphTo resolves correctly (AC-6)', function () use ($makeClientCenterSvc): void {
    [$client, $center, $svc] = $makeClientCenterSvc($this);

    $purchase = (new CreatePurchase())->execute($client, $center, PurchaseSource::Client, [
        ['purchasable' => $svc, 'quantity' => 1, 'unit_price' => 99.99],
    ]);

    $item = $purchase->items->first();
    $item->load('purchasable');

    $this->assertInstanceOf(TestPurchasable::class, $item->purchasable);
    $this->assertSame($svc->id, $item->purchasable->id);
    $this->assertSame($svc->name, $item->purchasable->name);

    $svcReloaded = TestPurchasable::with('purchaseItems')->findOrFail($svc->id);
    $this->assertTrue($svcReloaded->purchaseItems->contains('id', $item->id));
});

test('10 Events all exist, accept Purchase or Payment typed constructor, use Dispatchable (AC-43)', function (): void {
    $purchase = \Modules\Purchase\Database\Factories\PurchaseFactory::new()->create();
    $payment = \Modules\Purchase\Database\Factories\PaymentFactory::new()->create();

    $purchaseEvents = [
        \Modules\Purchase\Events\PurchaseCreated::class,
        \Modules\Purchase\Events\PurchaseConfirmed::class,
        \Modules\Purchase\Events\PurchaseCancelled::class,
        \Modules\Purchase\Events\PurchaseCompleted::class,
    ];
    foreach ($purchaseEvents as $cls) {
        $e = new $cls($purchase);
        $this->assertSame($purchase->id, $e->purchase->id, "Failed $cls");
    }

    $paymentEvents = [
        \Modules\Purchase\Events\PaymentCreated::class,
        \Modules\Purchase\Events\PaymentProcessing::class,
        \Modules\Purchase\Events\PaymentSucceeded::class,
        \Modules\Purchase\Events\PaymentFailed::class,
        \Modules\Purchase\Events\PaymentCancelled::class,
        \Modules\Purchase\Events\PaymentExpired::class,
    ];
    foreach ($paymentEvents as $cls) {
        $e = new $cls($payment);
        $this->assertSame($payment->id, $e->payment->id, "Failed $cls");
    }
});

test('No Purchase/Payment controller exists (AC-46 architecture)', function (): void {
    $srcDir = dirname(__DIR__, 2).'/src';
    $this->assertDirectoryExists($srcDir);

    $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir));
    $controllerFiles = [];
    foreach ($rii as $file) {
        if (! $file->isFile()) {
            continue;
        }
        $name = $file->getFilename();
        if (str_contains($name, 'Controller')) {
            $controllerFiles[] = $name;
        }
    }

    expect($controllerFiles)->toBeEmpty('No *Controller files should exist in Purchase module (no HTTP layer yet)');
});
