<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Merchant\Platform;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    // Ensure DB migrated (RefreshDatabase handles it)
});

$enumCases = function (string $enum, array $expectedValues): void {
    $cases = $enum::cases();
    $values = array_map(static fn ($c) => $c->value, $cases);

    expect($values)->toEqualCanonicalizing($expectedValues);
};

test('PurchaseSource enum has exactly client/center/system', function () use ($enumCases): void {
    $enumCases(PurchaseSource::class, ['client', 'center', 'system']);
});

test('PurchaseStatus enum has exactly pending/confirmed/cancelled/completed', function () use ($enumCases): void {
    $enumCases(PurchaseStatus::class, ['pending', 'confirmed', 'cancelled', 'completed']);
});

test('PaymentMethod enum has exactly online/cash_on_arrival/manual', function () use ($enumCases): void {
    $enumCases(PaymentMethod::class, ['online', 'cash_on_arrival', 'manual']);
});

test('PaymentStatus enum has exactly pending/processing/succeeded/failed/cancelled/expired/refunded', function () use ($enumCases): void {
    $enumCases(PaymentStatus::class, ['pending', 'processing', 'succeeded', 'failed', 'cancelled', 'expired', 'refunded']);
});

test('Purchase enum cast round-trip: status and source persist as enum objects', function (): void {
    $client = ClientFactory::new()->create();
    $platform = new Platform;

    $purchase = Purchase::create([
        'buyer_type' => $client->getMorphClass(),
        'buyer_id' => $client->getKey(),
        'merchant_type' => $platform->getMorphClass(),
        'merchant_id' => 1,
        'source' => PurchaseSource::Client,
        'status' => PurchaseStatus::Pending,
        'subtotal' => '100.00',
        'discount_amount' => '0.00',
        'tax_amount' => '0.00',
        'total_amount' => '100.00',
        'metadata' => null,
    ]);

    $purchase->status = PurchaseStatus::Confirmed;
    $purchase->source = PurchaseSource::Center;
    $purchase->save();

    $reloaded = Purchase::findOrFail($purchase->id);

    expect($reloaded->status)->toBe(PurchaseStatus::Confirmed);
    expect($reloaded->source)->toBe(PurchaseSource::Center);
    expect($reloaded->status->value)->toBe('confirmed');
    expect($reloaded->source->value)->toBe('center');
});

test('Payment enum cast round-trip: method and status persist as enum objects', function (): void {
    $purchase = PurchaseFactory::new()->create();

    $payment = Payment::create([
        'purchase_id' => $purchase->id,
        'amount' => 99.5,
        'method' => PaymentMethod::CashOnArrival,
        'status' => PaymentStatus::Pending,
        'payment_company' => null,
        'payment_type' => null,
    ]);

    $payment->status = PaymentStatus::Succeeded;
    $payment->method = PaymentMethod::Manual;
    $payment->paid_at = now();
    $payment->save();

    $reloaded = Payment::findOrFail($payment->id);

    expect($reloaded->method)->toBe(PaymentMethod::Manual);
    expect($reloaded->status)->toBe(PaymentStatus::Succeeded);
});
