<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Events\PurchaseCancelled;
use Modules\Purchase\Events\PurchaseCompleted;
use Modules\Purchase\Events\PurchaseConfirmed;
use Modules\Purchase\Services\PurchaseStateService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->city = \Modules\Support\Database\Factories\CityFactory::new()->create([
        'name' => ['en' => 'X', 'ar' => 'ي'],
    ]);
    $this->svc = new PurchaseStateService();
});

test('pending purchase can be confirmed', function (): void {
    Event::fake();

    $purchase = PurchaseFactory::new()->create(['status' => PurchaseStatus::Pending]);

    $confirmed = $this->svc->confirm($purchase);

    expect($confirmed->status)->toBe(PurchaseStatus::Confirmed);
    Event::assertDispatched(PurchaseConfirmed::class, 1);
});

test('confirmed purchase can be completed', function (): void {
    Event::fake();

    $purchase = PurchaseFactory::new()->create(['status' => PurchaseStatus::Confirmed]);

    $completed = $this->svc->complete($purchase);

    expect($completed->status)->toBe(PurchaseStatus::Completed);
    Event::assertDispatched(PurchaseCompleted::class, 1);
});

dataset('cancellable_statuses', [[PurchaseStatus::Pending], [PurchaseStatus::Confirmed]]);
test('pending and confirmed purchases can be cancelled', function (PurchaseStatus $status): void {
    Event::fake();

    $purchase = PurchaseFactory::new()->create(['status' => $status]);

    $cancelled = $this->svc->cancel($purchase);

    expect($cancelled->status)->toBe(PurchaseStatus::Cancelled);
    Event::assertDispatched(PurchaseCancelled::class, 1);
})->with('cancellable_statuses');

test('cancel on completed purchase throws and DB state unchanged (AC-25)', function (): void {
    $purchase = PurchaseFactory::new()->create(['status' => PurchaseStatus::Completed]);

    try {
        $this->svc->cancel($purchase);
        $this->fail('Expected exception not thrown');
    } catch (\InvalidArgumentException $e) {
        // good
    }

    $this->assertDatabaseHas('purchases', [
        'id' => $purchase->id,
        'status' => PurchaseStatus::Completed->value,
    ]);
});

test('confirm on non-pending throws (e.g. already confirmed)', function (): void {
    $purchase = PurchaseFactory::new()->create(['status' => PurchaseStatus::Confirmed]);

    $this->expectException(\InvalidArgumentException::class);
    $this->svc->confirm($purchase);
});

test('complete on pending purchase throws', function (): void {
    $purchase = PurchaseFactory::new()->create(['status' => PurchaseStatus::Pending]);

    $this->expectException(\InvalidArgumentException::class);
    $this->svc->complete($purchase);
});

test('each transition dispatches event exactly once and afterCommit (row visible in listener)', function (): void {
    $seenIds = [];
    Event::listen(PurchaseConfirmed::class, static function (PurchaseConfirmed $e) use (&$seenIds): void {
        $rowExists = \Modules\Purchase\Models\Purchase::query()
            ->whereKey($e->purchase->id)
            ->where('status', PurchaseStatus::Confirmed)
            ->exists();
        $seenIds['confirm'] = ['id' => $e->purchase->id, 'visible' => $rowExists];
    });
    Event::listen(PurchaseCompleted::class, static function (PurchaseCompleted $e) use (&$seenIds): void {
        $rowExists = \Modules\Purchase\Models\Purchase::query()
            ->whereKey($e->purchase->id)
            ->where('status', PurchaseStatus::Completed)
            ->exists();
        $seenIds['complete'] = ['id' => $e->purchase->id, 'visible' => $rowExists];
    });
    Event::listen(PurchaseCancelled::class, static function (PurchaseCancelled $e) use (&$seenIds): void {
        $rowExists = \Modules\Purchase\Models\Purchase::query()
            ->whereKey($e->purchase->id)
            ->where('status', PurchaseStatus::Cancelled)
            ->exists();
        $seenIds['cancel'] = ['id' => $e->purchase->id, 'visible' => $rowExists];
    });

    // Pending -> Cancelled
    $p1 = PurchaseFactory::new()->create(['status' => PurchaseStatus::Pending]);
    $this->svc->cancel($p1);
    expect($seenIds['cancel']['id'])->toBe($p1->id);
    expect($seenIds['cancel']['visible'])->toBeTrue();

    // Pending -> Confirmed -> Completed
    $p2 = PurchaseFactory::new()->create(['status' => PurchaseStatus::Pending]);
    $this->svc->confirm($p2);
    expect($seenIds['confirm']['id'])->toBe($p2->id);
    expect($seenIds['confirm']['visible'])->toBeTrue();

    $this->svc->complete($p2);
    expect($seenIds['complete']['id'])->toBe($p2->id);
    expect($seenIds['complete']['visible'])->toBeTrue();
});
