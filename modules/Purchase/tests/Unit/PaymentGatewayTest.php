<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Purchase\Contracts\PaymentGateway;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Gateways\FakeGateway;
use Modules\Purchase\Managers\PaymentGatewayManager;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->city = \Modules\Support\Database\Factories\CityFactory::new()->create([
        'name' => ['en' => 'G', 'ar' => 'ج'],
    ]);
});

test('PaymentGatewayManager driver(fake) returns object implementing PaymentGateway (AC-36)', function (): void {
    $manager = app(PaymentGatewayManager::class);
    $driver = $manager->driver('fake');

    expect($driver)->toBeInstanceOf(PaymentGateway::class);
    expect($driver)->toBeInstanceOf(FakeGateway::class);
});

test('FakeGateway::initialize returns non-empty array with checkout_url (AC-37)', function (): void {
    $purchase = PurchaseFactory::new()->create();
    $payment = PaymentFactory::new()->for($purchase, 'purchase')->online()->create();

    $gateway = new FakeGateway();
    $res = $gateway->initialize($purchase, $payment);

    expect($res)->toBeArray();
    expect($res)->toHaveKey('checkout_url');
    expect(is_string($res['checkout_url']) && strlen($res['checkout_url']) > 0)->toBeTrue();
    expect($res)->toHaveKey('provider_reference');
});

test('PaymentGatewayManager getDefaultDriver returns fake in testing env (AC-38)', function (): void {
    $manager = app(PaymentGatewayManager::class);
    expect($manager->getDefaultDriver())->toBe('fake');
});

test('FakeGateway setNextStatus then verify returns the status', function (): void {
    $payment = PaymentFactory::new()->create();
    $pid = (string) $payment->getKey();

    FakeGateway::clearNextStatus();
    FakeGateway::setNextStatus($pid, PaymentStatus::Succeeded);

    $gw = new FakeGateway();
    $got = $gw->verify($payment);
    expect($got)->toBe(PaymentStatus::Succeeded);

    FakeGateway::clearNextStatus($pid);
    $back = $gw->verify($payment);
    expect($back)->toBe(PaymentStatus::Pending);
});

test('FakeGateway cancel returns Cancelled, refund returns true, capture defaults to Succeeded', function (): void {
    $payment = PaymentFactory::new()->create();
    $gw = new FakeGateway();

    expect($gw->cancel($payment))->toBe(PaymentStatus::Cancelled);
    expect($gw->capture($payment))->toBe(PaymentStatus::Succeeded);
    expect($gw->refund($payment, 100.0))->toBeTrue();
});
