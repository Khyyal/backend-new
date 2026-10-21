<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Events\PurchaseConfirmed;
use Modules\Purchase\Services\PaymentStateService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->payments = app(PaymentStateService::class);
    $this->purchase = PurchaseFactory::new()->create([
        'status' => PurchaseStatus::Pending,
        'subtotal' => 300,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 300,
    ]);
});

test('a payment covering the total confirms the pending purchase', function (): void {
    Event::fake([PurchaseConfirmed::class]);
    $payment = PaymentFactory::new()->online()->create(['purchase_id' => $this->purchase->id, 'amount' => 300]);

    $this->payments->markSucceeded($payment);

    expect($this->purchase->fresh()->status)->toBe(PurchaseStatus::Confirmed);
    Event::assertDispatched(PurchaseConfirmed::class, 1);
});

test('a partial payment leaves the purchase pending until the rest succeeds', function (): void {
    $first = PaymentFactory::new()->online()->create(['purchase_id' => $this->purchase->id, 'amount' => 100]);
    $second = PaymentFactory::new()->online()->create(['purchase_id' => $this->purchase->id, 'amount' => 200]);

    $this->payments->markSucceeded($first);
    expect($this->purchase->fresh()->status)->toBe(PurchaseStatus::Pending);

    $this->payments->markSucceeded($second);
    expect($this->purchase->fresh()->status)->toBe(PurchaseStatus::Confirmed);
});

test('failed payments do not count towards the total', function (): void {
    $failed = PaymentFactory::new()->online()->create(['purchase_id' => $this->purchase->id, 'amount' => 300]);
    $this->payments->markFailed($failed);
    $partial = PaymentFactory::new()->online()->create(['purchase_id' => $this->purchase->id, 'amount' => 100]);

    $this->payments->markSucceeded($partial);

    expect($this->purchase->fresh()->status)->toBe(PurchaseStatus::Pending);
});

test('a purchase that is no longer pending is left untouched', function (PurchaseStatus $status): void {
    Event::fake([PurchaseConfirmed::class]);
    $this->purchase->update(['status' => $status]);
    $payment = PaymentFactory::new()->online()->create(['purchase_id' => $this->purchase->id, 'amount' => 300]);

    expect($this->payments->markSucceeded($payment)->status)->toBe(PaymentStatus::Succeeded)
        ->and($this->purchase->fresh()->status)->toBe($status);
    Event::assertNotDispatched(PurchaseConfirmed::class);
})->with([PurchaseStatus::Confirmed, PurchaseStatus::Cancelled, PurchaseStatus::Completed]);
