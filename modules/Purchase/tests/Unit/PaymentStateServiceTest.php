<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Carbon;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Events\PaymentCancelled;
use Modules\Purchase\Events\PaymentExpired;
use Modules\Purchase\Events\PaymentFailed;
use Modules\Purchase\Events\PaymentProcessing;
use Modules\Purchase\Events\PaymentSucceeded;
use Modules\Purchase\Services\PaymentStateService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->city = \Modules\Support\Database\Factories\CityFactory::new()->create([
        'name' => ['en' => 'Y', 'ar' => 'ز'],
    ]);
    $this->svc = new PaymentStateService();
});

test('pending -> processing -> succeeds via PaymentStateService (AC-27)', function (): void {
    Event::fake();

    $payment = PaymentFactory::new()->create(['status' => PaymentStatus::Pending]);

    $processing = $this->svc->markProcessing($payment);
    expect($processing->status)->toBe(PaymentStatus::Processing);
    Event::assertDispatched(PaymentProcessing::class, 1);

    $succeeded = $this->svc->markSucceeded($processing);
    expect($succeeded->status)->toBe(PaymentStatus::Succeeded);
    expect($succeeded->paid_at)->not->toBeNull();
    expect($succeeded->paid_at)->toBeInstanceOf(Carbon::class);
    Event::assertDispatched(PaymentSucceeded::class, 1);
});

test('succeeded payment cannot regress to failed (immutable, AC-28)', function (): void {
    $succeededPayment = PaymentFactory::new()->succeeded()->create();

    try {
        $this->svc->markFailed($succeededPayment);
        $this->fail('Expected exception not thrown');
    } catch (\InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('Succeeded payments are immutable');
    }

    $this->assertDatabaseHas('payments', [
        'id' => $succeededPayment->id,
        'status' => PaymentStatus::Succeeded->value,
    ]);
});

dataset('terminal_non_success_from_pending_or_processing', [
    ['markFailed', PaymentFailed::class],
    ['cancel', PaymentCancelled::class],
    ['expire', PaymentExpired::class],
]);

test('pending can transition to failed/cancelled/expired (AC-29 pending paths)', function (string $method, string $eventClass): void {
    Event::fake();
    $p = PaymentFactory::new()->create(['status' => PaymentStatus::Pending]);

    $res = $this->svc->$method($p);
    expect($res->status->value)->toBe(
        match($eventClass) {
            PaymentFailed::class => 'failed',
            PaymentCancelled::class => 'cancelled',
            PaymentExpired::class => 'expired',
        }
    );
    Event::assertDispatched($eventClass, 1);
})->with('terminal_non_success_from_pending_or_processing');

test('processing can transition to failed/cancelled/expired (AC-29 processing paths)', function (): void {
    Event::fake();
    $pF = PaymentFactory::new()->create(['status' => PaymentStatus::Processing]);
    $pC = PaymentFactory::new()->create(['status' => PaymentStatus::Processing]);
    $pE = PaymentFactory::new()->create(['status' => PaymentStatus::Processing]);

    $this->svc->markFailed($pF);
    $this->svc->cancel($pC);
    $this->svc->expire($pE);

    expect($pF->fresh()->status)->toBe(PaymentStatus::Failed);
    expect($pC->fresh()->status)->toBe(PaymentStatus::Cancelled);
    expect($pE->fresh()->status)->toBe(PaymentStatus::Expired);

    Event::assertDispatched(PaymentFailed::class, 1);
    Event::assertDispatched(PaymentCancelled::class, 1);
    Event::assertDispatched(PaymentExpired::class, 1);
});

test('succeeded payment has paid_at set after markSucceeded (AC-30)', function (): void {
    $p = PaymentFactory::new()->create(['status' => PaymentStatus::Pending, 'paid_at' => null]);

    $res = $this->svc->markSucceeded($p);

    expect($res->paid_at)->not->toBeNull();
    $this->assertDatabaseHas('payments', [
        'id' => $res->id,
        'status' => PaymentStatus::Succeeded->value,
    ]);
    expect($res->paid_at)->toBeInstanceOf(Carbon::class);
});

test('each transition dispatches its event once afterCommit with DB state visible', function (): void {
    $hits = [];
    Event::listen(static function (\Modules\Purchase\Events\PaymentProcessing $e) use (&$hits): void {
        $hits['PaymentProcessing'] = ['visible' => \Modules\Purchase\Models\Payment::query()->whereKey($e->payment->id)->where('status', PaymentStatus::Processing)->exists()];
    });
    Event::listen(static function (\Modules\Purchase\Events\PaymentSucceeded $e) use (&$hits): void {
        $hits['PaymentSucceeded'] = ['visible' => \Modules\Purchase\Models\Payment::query()->whereKey($e->payment->id)->where('status', PaymentStatus::Succeeded)->exists()];
    });
    Event::listen(static function (\Modules\Purchase\Events\PaymentFailed $e) use (&$hits): void {
        $hits['PaymentFailed'] = ['visible' => \Modules\Purchase\Models\Payment::query()->whereKey($e->payment->id)->where('status', PaymentStatus::Failed)->exists()];
    });
    Event::listen(static function (\Modules\Purchase\Events\PaymentCancelled $e) use (&$hits): void {
        $hits['PaymentCancelled'] = ['visible' => \Modules\Purchase\Models\Payment::query()->whereKey($e->payment->id)->where('status', PaymentStatus::Cancelled)->exists()];
    });
    Event::listen(static function (\Modules\Purchase\Events\PaymentExpired $e) use (&$hits): void {
        $hits['PaymentExpired'] = ['visible' => \Modules\Purchase\Models\Payment::query()->whereKey($e->payment->id)->where('status', PaymentStatus::Expired)->exists()];
    });

    $p = PaymentFactory::new()->create(['status' => PaymentStatus::Pending]);
    $this->svc->markProcessing($p);
    $this->svc->markSucceeded($p);

    $p2 = PaymentFactory::new()->create(['status' => PaymentStatus::Pending]);
    $this->svc->markFailed($p2);

    $p3 = PaymentFactory::new()->create(['status' => PaymentStatus::Pending]);
    $this->svc->cancel($p3);

    $p4 = PaymentFactory::new()->create(['status' => PaymentStatus::Pending]);
    $this->svc->expire($p4);

    foreach (['PaymentProcessing', 'PaymentSucceeded', 'PaymentFailed', 'PaymentCancelled', 'PaymentExpired'] as $k) {
        expect($hits[$k]['visible'] ?? null)->toBeTrue("$k listener should see row");
    }
});

test('failed payment -> new payment succeeds: retry scenario (AC-34) and failed unchanged', function (): void {
    $purchase = PurchaseFactory::new()->create();
    $payment1 = PaymentFactory::new()->for($purchase, 'purchase')->failed()->create();

    // Create a second payment (retry)
    $payment2 = PaymentFactory::new()->for($purchase, 'purchase')->create([
        'status' => PaymentStatus::Pending,
    ]);

    // Succeed the 2nd
    $this->svc->markSucceeded($payment2);

    // Payment 1 remains failed
    expect($payment1->fresh()->status)->toBe(PaymentStatus::Failed);
    expect($payment2->fresh()->status)->toBe(PaymentStatus::Succeeded);
    // Purchase still has its original status (NOT cancelled)
    expect($purchase->fresh()->status)->toBe($purchase->status);
});

test('Failed payment does NOT auto-cancel its Purchase (AC-33)', function (): void {
    $purchase = PurchaseFactory::new()->create(['status' => \Modules\Purchase\Enums\PurchaseStatus::Confirmed]);
    $payment = PaymentFactory::new()->for($purchase, 'purchase')->create([
        'status' => PaymentStatus::Pending,
    ]);

    $this->svc->markFailed($payment);

    $purchaseFresh = $purchase->fresh();
    expect($purchaseFresh->status)->toBe(\Modules\Purchase\Enums\PurchaseStatus::Confirmed);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);
});
