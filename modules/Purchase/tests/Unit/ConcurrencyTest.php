<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Services\PaymentStateService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->city = \Modules\Support\Database\Factories\CityFactory::new()->create([
        'name' => ['en' => 'Concurrency City', 'ar' => 'مدينة التزامن'],
    ]);
    $this->svc = new PaymentStateService();
});

test('Two sequential markSucceeded processes: first succeeds, second is idempotent (no regression AC-42)', function (): void {
    // First transitions, second (to same state) is idempotent: returns unchanged, no throw
    $payment = PaymentFactory::new()->create(['status' => PaymentStatus::Pending]);

    $first = $this->svc->markSucceeded($payment);
    expect($first->status)->toBe(PaymentStatus::Succeeded);
    expect($first->paid_at)->not->toBeNull();

    $second = $this->svc->markSucceeded($payment);
    expect($second->status)->toBe(PaymentStatus::Succeeded);
    expect($second->id)->toBe($first->id);
});

test('Concurrent markSucceeded via two separate DB transactions (simulated race using fresh reload)', function (): void {
    $payment = PaymentFactory::new()->create(['status' => PaymentStatus::Pending]);
    $pid = $payment->id;

    // Transaction A: transitions pending -> succeeded
    DB::transaction(function () use ($pid): void {
        $p = \Modules\Purchase\Models\Payment::query()
            ->whereKey($pid)
            ->lockForUpdate()
            ->firstOrFail();
        $p->status = PaymentStatus::Succeeded;
        $p->paid_at = now();
        $p->save();
    });

    // Transaction B (after commit): state service reloads via lockForUpdate and sees Succeeded
    $staleRef = \Modules\Purchase\Models\Payment::findOrFail($pid);
    $staleRef->status = PaymentStatus::Pending; // make it "stale" as if loaded before A committed

    // Calling markSucceeded() with stale $payment still reads actual DB row (Succeeded)
    // Because target is Succeeded (same) -> idempotent no-op return. DB is not corrupted.
    $result = $this->svc->markSucceeded($staleRef);
    expect($result->status)->toBe(PaymentStatus::Succeeded);

    // DB value is never corrupted (no regression)
    $this->assertDatabaseHas('payments', [
        'id' => $pid,
        'status' => PaymentStatus::Succeeded->value,
    ]);
});

test('markFailed on succeeded payment always throws (never writes failed status, AC-42 immutable)', function (): void {
    $succeeded = PaymentFactory::new()->succeeded()->create();

    try {
        $this->svc->markFailed($succeeded);
        $this->fail('Expected InvalidArgumentException not thrown');
    } catch (\InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('Succeeded payments are immutable');
    }

    $this->assertDatabaseHas('payments', [
        'id' => $succeeded->id,
        'status' => PaymentStatus::Succeeded->value,
    ]);
});

test('PurchaseStateService: cancel vs complete race cannot produce both', function (): void {
    $purchase = \Modules\Purchase\Database\Factories\PurchaseFactory::new()->create([
        'status' => \Modules\Purchase\Enums\PurchaseStatus::Confirmed,
    ]);
    $purchaseId = $purchase->id;

    // Simulate: first transaction runs complete()
    $stateSvc = new \Modules\Purchase\Services\PurchaseStateService();
    $completed = $stateSvc->complete($purchase);

    expect($completed->status)->toBe(\Modules\Purchase\Enums\PurchaseStatus::Completed);

    // cancel on Completed must throw
    $this->expectException(\InvalidArgumentException::class);
    $stalePurchase = \Modules\Purchase\Models\Purchase::findOrFail($purchaseId);
    $stateSvc->cancel($stalePurchase);
});
