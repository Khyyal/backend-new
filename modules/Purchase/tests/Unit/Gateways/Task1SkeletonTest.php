<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Purchase\Contracts\PaymentGateway;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Exceptions\PaymentAuthorizationException;
use Modules\Purchase\Exceptions\PaymentGatewayException;
use Modules\Purchase\Exceptions\PaymentInitializationException;
use Modules\Purchase\Exceptions\PaymentProviderUnavailableException;
use Modules\Purchase\Exceptions\PaymentProviderValidationException;
use Modules\Purchase\Exceptions\PaymentStateConflictException;
use Modules\Purchase\Exceptions\PaymentVerificationException;
use Modules\Purchase\Gateways\FakeGateway;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('T1-TR1: PaymentGateway interface exposes exactly 7 public methods', function (): void {
    $reflection = new ReflectionClass(PaymentGateway::class);
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

    $names = array_map(static fn (ReflectionMethod $m): string => $m->getName(), $methods);
    sort($names);

    $expected = ['authorize', 'cancel', 'capture', 'initialize', 'refund', 'sync', 'verify'];
    sort($expected);

    expect($names)->toBe($expected);
});

test('T1-TR2: FakeGateway implements PaymentGateway and exposes all 7 methods', function (): void {
    $implements = class_implements(FakeGateway::class);
    expect($implements)->toContain(PaymentGateway::class);

    $reflection = new ReflectionClass(FakeGateway::class);
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    $names = array_map(static fn (ReflectionMethod $m): string => $m->getName(), $methods);

    foreach (['initialize', 'sync', 'authorize', 'verify', 'capture', 'cancel', 'refund'] as $name) {
        expect($names)->toContain($name);
    }
});

test('T1-TR2: FakeGateway sync returns Pending by default and setNextStatus override', function (): void {
    $payment = PaymentFactory::new()->create();
    $pid = (string) $payment->getKey();

    FakeGateway::clearNextStatus();
    $gw = new FakeGateway();

    expect($gw->sync($payment))->toBe(PaymentStatus::Pending);

    FakeGateway::setNextStatus($pid, PaymentStatus::Succeeded);
    expect($gw->sync($payment))->toBe(PaymentStatus::Succeeded);

    FakeGateway::clearNextStatus($pid);
});

test('T1-TR2: FakeGateway authorize returns Processing by default', function (): void {
    $payment = PaymentFactory::new()->create();
    $pid = (string) $payment->getKey();

    FakeGateway::clearNextStatus();
    $gw = new FakeGateway();

    expect($gw->authorize($payment))->toBe(PaymentStatus::Processing);

    FakeGateway::setNextStatus($pid, PaymentStatus::Succeeded);
    expect($gw->authorize($payment))->toBe(PaymentStatus::Succeeded);

    FakeGateway::clearNextStatus($pid);
});

test('T1-TR3: All 7 exception classes exist and 6 subtypes instanceof base', function (): void {
    $classes = [
        PaymentGatewayException::class,
        PaymentInitializationException::class,
        PaymentProviderUnavailableException::class,
        PaymentProviderValidationException::class,
        PaymentVerificationException::class,
        PaymentStateConflictException::class,
        PaymentAuthorizationException::class,
    ];

    foreach ($classes as $cls) {
        expect(class_exists($cls))->toBeTrue("Class $cls does not exist");
    }

    $subtypes = [
        PaymentInitializationException::class,
        PaymentProviderUnavailableException::class,
        PaymentProviderValidationException::class,
        PaymentVerificationException::class,
        PaymentStateConflictException::class,
        PaymentAuthorizationException::class,
    ];

    foreach ($subtypes as $cls) {
        $instance = new $cls('test message');
        expect($instance)->toBeInstanceOf(PaymentGatewayException::class);
        expect(get_parent_class($instance))->toBe(PaymentGatewayException::class);
    }
});

test('T1-TR3: PaymentGatewayException extends RuntimeException', function (): void {
    $instance = new PaymentGatewayException('boom');
    expect($instance)->toBeInstanceOf(RuntimeException::class);
});
