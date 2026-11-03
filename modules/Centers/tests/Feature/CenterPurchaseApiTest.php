<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Database\Factories\PurchaseItemFactory;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Services\Database\Factories\PriceOptionFactory;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Services\Database\Factories\TrainerFactory;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->center = CenterFactory::new()->create();
    $this->user = UserFactory::new()->create();
    $this->center->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => true,
    ]);
    $this->url = "/api/v1/centers/{$this->center->id}/purchases";

    $this->purchase = fn (array $attributes = [], $center = null) => PurchaseFactory::new()->create([
        'merchant_type' => ($center ?? $this->center)->getMorphClass(),
        'merchant_id' => ($center ?? $this->center)->id,
        'status' => PurchaseStatus::Confirmed,
        ...$attributes,
    ]);
});

test('guests and users of other centers are rejected', function (): void {
    $purchase = ($this->purchase)();

    $this->getJson($this->url)->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson($this->url)
        ->assertForbidden();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson("{$this->url}/{$purchase->id}")
        ->assertForbidden();
});

test('lists only the center purchases, newest first, with a status filter', function (): void {
    $old = ($this->purchase)(['created_at' => '2026-09-01 10:00:00']);
    $new = ($this->purchase)(['created_at' => '2026-10-01 10:00:00', 'status' => PurchaseStatus::Pending]);
    ($this->purchase)([], CenterFactory::new()->create());

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.1.id', $old->id)
        ->assertJsonPath('data.1.client.id', $old->buyer_id);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?status=pending")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $new->id);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?status=bogus")
        ->assertUnprocessable();
});

test('shows a purchase with its items and payments', function (): void {
    $purchase = ($this->purchase)();
    PurchaseItemFactory::new()->count(2)->create(['purchase_id' => $purchase->id]);
    PaymentFactory::new()->online()->succeeded()->create(['purchase_id' => $purchase->id, 'amount' => 100]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/{$purchase->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $purchase->id)
        ->assertJsonPath('data.items_count', 2)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonCount(1, 'data.payments')
        ->assertJsonPath('data.payments.0.method', 'online')
        ->assertJsonPath('data.payments.0.status', 'succeeded')
        ->assertJsonMissingPath('data.payments.0.provider_data');
});

test('shows the reservation and package details of each item', function (): void {
    $service = ServiceFactory::new()->create(['center_id' => $this->center->id]);
    $priceOption = PriceOptionFactory::new()->create(['service_id' => $service->id]);
    $trainer = TrainerFactory::new()->create(['center_id' => $this->center->id]);
    $reservation = ReservationFactory::new()->forService($service)->at('2026-11-10', '09:00:00', '10:00:00')->create([
        'trainer_id' => $trainer->id,
        'price_option_id' => $priceOption->id,
    ]);
    $package = ClientPackageFactory::new()->create(['service_id' => $service->id, 'price_option_id' => $priceOption->id]);

    $purchase = ($this->purchase)();
    foreach ([$reservation, $package] as $purchasable) {
        PurchaseItemFactory::new()->create([
            'purchase_id' => $purchase->id,
            'purchasable_type' => $purchasable->getMorphClass(),
            'purchasable_id' => $purchasable->id,
        ]);
    }
    PurchaseItemFactory::new()->create(['purchase_id' => $purchase->id]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/{$purchase->id}")
        ->assertOk()
        ->assertJsonPath('data.items.0.details.service.id', $service->id)
        ->assertJsonPath('data.items.0.details.trainer.id', $trainer->id)
        ->assertJsonPath('data.items.0.details.price_option.id', $priceOption->id)
        ->assertJsonPath('data.items.0.details.date', '2026-11-10')
        ->assertJsonPath('data.items.0.details.start_time', '09:00')
        ->assertJsonPath('data.items.0.details.end_time', '10:00')
        ->assertJsonPath('data.items.1.details.service.id', $service->id)
        ->assertJsonPath('data.items.1.details.price_option.id', $priceOption->id)
        ->assertJsonPath('data.items.1.details.total_sessions', $package->total_sessions)
        ->assertJsonPath('data.items.1.details.remaining_sessions', $package->remaining_sessions)
        ->assertJsonPath('data.items.2.details', null);
});

test('a purchase of another center is not found', function (): void {
    $other = ($this->purchase)([], CenterFactory::new()->create());

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/{$other->id}")
        ->assertNotFound();
});

test('lists the method and status of the latest payment', function (): void {
    $paid = ($this->purchase)(['created_at' => '2026-10-01 10:00:00']);
    PaymentFactory::new()->online()->failed()->create(['purchase_id' => $paid->id]);
    PaymentFactory::new()->online()->succeeded()->create(['purchase_id' => $paid->id]);
    ($this->purchase)(['created_at' => '2026-09-01 10:00:00']);

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.0.payment_method', 'online')
        ->assertJsonPath('data.0.payment_status', 'succeeded')
        ->assertJsonPath('data.1.payment_method', null)
        ->assertJsonPath('data.1.payment_status', null);
});

test('filters by service and creation date range', function (): void {
    $service = ServiceFactory::new()->create(['center_id' => $this->center->id]);
    $otherService = ServiceFactory::new()->create(['center_id' => $this->center->id]);
    $withItem = function ($purchasable, string $createdAt) {
        $purchase = ($this->purchase)(['created_at' => $createdAt]);
        PurchaseItemFactory::new()->create([
            'purchase_id' => $purchase->id,
            'purchasable_type' => $purchasable->getMorphClass(),
            'purchasable_id' => $purchasable->id,
        ]);

        return $purchase;
    };

    $reservation = $withItem(ReservationFactory::new()->create(['service_id' => $service->id]), '2026-10-05 10:00:00');
    $package = ClientPackageFactory::new()->create();
    $package->update(['service_id' => $service->id]);
    $packagePurchase = $withItem($package, '2026-10-20 10:00:00');
    $other = $withItem(ReservationFactory::new()->create(['service_id' => $otherService->id]), '2026-10-10 10:00:00');

    $ids = fn (string $query): array => collect($this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?{$query}")
        ->assertOk()
        ->json('data'))->pluck('id')->sort()->values()->all();

    expect($ids("service_id={$service->id}"))->toBe([$reservation->id, $packagePurchase->id])
        ->and($ids('from=2026-10-05&to=2026-10-10'))->toBe([$reservation->id, $other->id])
        ->and($ids("service_id={$service->id}&from=2026-10-06"))->toBe([$packagePurchase->id]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?from=2026-10-10&to=2026-10-01&service_id=abc")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to', 'service_id']);
});

test('searches by client name or phone number', function (): void {
    $sara = ClientFactory::new()->create(['first_name' => 'Sara', 'last_name' => 'Alharbi', 'phone_number' => '+966500000001']);
    $omar = ClientFactory::new()->create(['first_name' => 'Omar', 'last_name' => 'Zahrani', 'phone_number' => '+966500000002']);
    $saraPurchase = ($this->purchase)(['buyer_type' => $sara->getMorphClass(), 'buyer_id' => $sara->id]);
    $omarPurchase = ($this->purchase)(['buyer_type' => $omar->getMorphClass(), 'buyer_id' => $omar->id]);

    $ids = fn (string $q): array => collect($this->actingAs($this->user, 'center_user')
        ->getJson($this->url.'?q='.urlencode($q))
        ->assertOk()
        ->json('data'))->pluck('id')->all();

    expect($ids('sara alh'))->toBe([$saraPurchase->id])
        ->and($ids('0000002'))->toBe([$omarPurchase->id])
        ->and($ids('nobody'))->toBe([]);
});
