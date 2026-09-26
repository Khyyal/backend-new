<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Actions\CreatePayment;
use Modules\Purchase\Actions\CreatePurchase;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Events\PaymentSucceeded;
use Modules\Purchase\Gateways\FakeWebhookEventHandler;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Purchase\Services\WebhookProcessorService;
use Illuminate\Support\Facades\Schema;
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
        'name' => ['en' => 'Webhook City', 'ar' => 'مدينة الويب هوك'],
    ]);

    $this->webhookSvc = new WebhookProcessorService(new PaymentStateService(), [
        'fake' => FakeWebhookEventHandler::class,
    ]);
});

afterEach(function (): void {
    Schema::dropIfExists('test_services');
});

$makePurchaseAndPaymentWithOrderId = function ($test, string $orderId) {
    $center = \Modules\Centers\Database\Factories\CenterFactory::new()->create(['city_id' => $test->city->id]);
    $client = ClientFactory::new()->create();
    $svc = TestPurchasable::create(['name' => 'Svc', 'price' => 300]);

    $purchase = (new CreatePurchase())->execute(
        buyer: $client,
        merchant: $center,
        source: PurchaseSource::Client,
        items: [
            ['purchasable' => $svc, 'quantity' => 1, 'unit_price' => 300.0],
        ],
    );

    $payment = (new CreatePayment())->execute(
        purchase: $purchase,
        method: PaymentMethod::Online,
        amount: 300.0,
        paymentCompany: 'fake',
        paymentType: 'card',
    );

    $payment->payment_order_id = $orderId;
    $payment->save();

    return $payment;
};

test('Unique webhook_event(gateway, external_event_id) blocks duplicates (AC-39)', function (): void {
    \Modules\Purchase\Models\WebhookEvent::create([
        'gateway' => 'fake',
        'external_event_id' => 'evt_unique_01',
        'event_type' => 'payment.succeeded',
        'payload' => ['k' => 1],
        'attempts' => 1,
    ]);

    $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

    \Modules\Purchase\Models\WebhookEvent::create([
        'gateway' => 'fake',
        'external_event_id' => 'evt_unique_01',
        'event_type' => 'payment.succeeded',
        'payload' => ['k' => 2],
        'attempts' => 0,
    ]);
});

test('WebhookProcessorService.process() twice with same event results in ONE payment state change (AC-40)', function () use ($makePurchaseAndPaymentWithOrderId): void {
    Event::fake();

    $orderId = 'ORD-WEBHOOK-40-001';
    $payment = $makePurchaseAndPaymentWithOrderId($this, $orderId);

    $payload = [
        'payment_order_id' => $orderId,
        'status' => PaymentStatus::Succeeded->value,
    ];

    $extId = 'evt_40_unique';

    $this->webhookSvc->process('fake', $extId, 'payment.succeeded', $payload);
    $this->webhookSvc->process('fake', $extId, 'payment.succeeded', $payload);

    Event::assertDispatched(PaymentSucceeded::class, 1);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

test('processed_at is set after first process; second call leaves it unchanged (AC-41)', function () use ($makePurchaseAndPaymentWithOrderId): void {
    $orderId = 'ORD-41-A';
    $payment = $makePurchaseAndPaymentWithOrderId($this, $orderId);

    $extId = 'evt_41_aaaa';
    $payload = [
        'payment_order_id' => $orderId,
        'status' => PaymentStatus::Succeeded->value,
    ];

    $this->webhookSvc->process('fake', $extId, 'payment.succeeded', $payload);

    $afterFirst = \Modules\Purchase\Models\WebhookEvent::query()
        ->where('gateway', 'fake')
        ->where('external_event_id', $extId)
        ->firstOrFail();

    expect($afterFirst->processed_at)->not->toBeNull();
    expect($afterFirst->attempts)->toBe(1);

    $firstTimestamp = $afterFirst->processed_at->toDateTimeString();

    $this->webhookSvc->process('fake', $extId, 'payment.succeeded', $payload);

    $afterSecond = $afterFirst->fresh();
    $secondTimestamp = $afterSecond->processed_at->toDateTimeString();

    expect($secondTimestamp)->toBe($firstTimestamp);
    expect($afterSecond->attempts)->toBe(1);
});

test('Webhook handler returns non-existent payment_order_id sets failed_at and increments attempts', function (): void {
    $extId = 'evt_bad_order';
    $payload = [
        'payment_order_id' => 'does-not-exist-order-123456',
        'status' => PaymentStatus::Succeeded->value,
    ];

    $caught = false;
    try {
        $this->webhookSvc->process('fake', $extId, 'payment.succeeded', $payload);
    } catch (\InvalidArgumentException $e) {
        $caught = true;
    }

    $this->assertTrue($caught, 'Expected InvalidArgumentException to bubble up after marking failed');

    $row = \Modules\Purchase\Models\WebhookEvent::query()
        ->where('gateway', 'fake')
        ->where('external_event_id', $extId)
        ->firstOrFail();

    expect($row->failed_at)->not->toBeNull();
    expect($row->processed_at)->toBeNull();
    expect($row->attempts)->toBe(1);
});

test('FakeWebhookEventHandler correctly maps payload fields (TR-13 support)', function (): void {
    $ev = \Modules\Purchase\Models\WebhookEvent::make([
        'payload' => [
            'payment_order_id' => 'ORD-HANDLER-1',
            'status' => PaymentStatus::Failed->value,
        ],
    ]);

    $handler = new FakeWebhookEventHandler();
    $res = $handler->handle($ev);

    expect($res)->toBeArray();
    expect($res['payment_order_id'])->toBe('ORD-HANDLER-1');
    expect($res['payment_status'])->toBe(PaymentStatus::Failed);
});
