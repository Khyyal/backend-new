<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Database\Factories\PurchaseItemFactory;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ReservationStatus;
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
    $this->url = "/api/v1/centers/{$this->center->id}/analytics";

    $this->purchase = fn (array $attributes = [], $center = null) => PurchaseFactory::new()->create([
        'merchant_type' => ($center ?? $this->center)->getMorphClass(),
        'merchant_id' => ($center ?? $this->center)->id,
        'status' => PurchaseStatus::Confirmed,
        ...$attributes,
    ]);

    $this->item = fn ($purchase, $purchasable) => PurchaseItemFactory::new()->create([
        'purchase_id' => $purchase->id,
        'purchasable_type' => $purchasable->getMorphClass(),
        'purchasable_id' => $purchasable->id,
    ]);
});

test('guests, users of other centers and users without analytics.view are rejected', function (): void {
    $this->getJson($this->url)->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson($this->url)
        ->assertForbidden();

    $member = UserFactory::new()->create();
    $this->center->users()->attach($member->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $member->syncCenterPermissions($this->center, [CenterUserPermission::ViewTrainingPurchases]);

    $this->actingAs($member, 'center_user')->getJson($this->url)->assertForbidden();

    $member->syncCenterPermissions($this->center, [CenterUserPermission::ViewAnalytics]);

    $this->actingAs($member, 'center_user')->getJson($this->url)->assertOk();
});

test('defaults to the last 30 days compared with the 30 days before', function (): void {
    $this->travelTo('2026-10-10 12:00:00');

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.period', [
            'from' => '2026-09-11',
            'to' => '2026-10-10',
            'previous_from' => '2026-08-12',
            'previous_to' => '2026-09-10',
        ]);
});

test('computes the KPIs with their growth over the previous period', function (): void {
    [$returning, $new, $other] = ClientFactory::new()->count(3)->create();
    $buyer = fn ($client) => ['buyer_type' => $client->getMorphClass(), 'buyer_id' => $client->id];

    ($this->purchase)([...$buyer($returning), 'created_at' => '2026-09-25 10:00:00', 'total_amount' => 1000]);
    ($this->purchase)([...$buyer($returning), 'created_at' => '2026-10-02 10:00:00', 'total_amount' => 2000]);
    ($this->purchase)([...$buyer($new), 'created_at' => '2026-10-05 10:00:00', 'total_amount' => 3000, 'status' => PurchaseStatus::Completed]);
    ($this->purchase)([...$buyer($new), 'created_at' => '2026-10-06 10:00:00', 'total_amount' => 9999, 'status' => PurchaseStatus::Pending]);
    ($this->purchase)([...$buyer($other), 'created_at' => '2026-10-08 10:00:00', 'total_amount' => 9999, 'status' => PurchaseStatus::Cancelled]);
    ($this->purchase)([...$buyer($other), 'created_at' => '2026-10-11 10:00:00', 'total_amount' => 9999]);
    ($this->purchase)([...$buyer($other), 'created_at' => '2026-10-03 10:00:00', 'total_amount' => 9999], CenterFactory::new()->create());

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?from=2026-10-01&to=2026-10-10")
        ->assertOk()
        ->assertJsonPath('data.period.previous_from', '2026-09-21')
        ->assertJsonPath('data.period.previous_to', '2026-09-30')
        ->assertJsonPath('data.kpis.purchases_count', ['value' => 2, 'previous' => 1, 'growth' => 100])
        ->assertJsonPath('data.kpis.total_revenue', ['value' => 5000, 'previous' => 1000, 'growth' => 400])
        ->assertJsonPath('data.kpis.new_clients', ['value' => 1, 'previous' => 1, 'growth' => 0])
        ->assertJsonPath('data.kpis.repeat_client_rate', ['value' => 50, 'previous' => 0, 'growth' => null]);
});

test('ranks services by purchases and trainers by reservations', function (): void {
    [$popular, $quiet, $cancelled] = ServiceFactory::new()->count(3)->create(['center_id' => $this->center->id]);
    [$busy, $idle] = TrainerFactory::new()->count(2)->create(['center_id' => $this->center->id]);
    $inRange = ['created_at' => '2026-10-05 10:00:00'];

    $first = ($this->purchase)($inRange);
    ($this->item)($first, ReservationFactory::new()->forService($popular)->create(['trainer_id' => $busy->id, ...$inRange]));
    ($this->item)($first, ReservationFactory::new()->forService($popular)->create(['trainer_id' => $busy->id, ...$inRange]));
    ($this->item)(($this->purchase)($inRange), ClientPackageFactory::new()->create(['service_id' => $popular->id]));
    ($this->item)(($this->purchase)($inRange), ReservationFactory::new()->forService($quiet)->create(['trainer_id' => $idle->id, ...$inRange]));
    ($this->item)(
        ($this->purchase)([...$inRange, 'status' => PurchaseStatus::Cancelled]),
        ReservationFactory::new()->forService($cancelled)->create(['trainer_id' => $idle->id, 'status' => ReservationStatus::Cancelled, ...$inRange]),
    );
    ReservationFactory::new()->forService($quiet)->create(['trainer_id' => $idle->id, 'created_at' => '2026-11-01 10:00:00']);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?from=2026-10-01&to=2026-10-10")
        ->assertOk()
        ->assertJsonCount(2, 'data.top_services')
        ->assertJsonPath('data.top_services.0.service_id', $popular->id)
        ->assertJsonPath('data.top_services.0.purchases_count', 2)
        ->assertJsonPath('data.top_services.0.type', $popular->type->value)
        ->assertJsonPath('data.top_services.1.service_id', $quiet->id)
        ->assertJsonPath('data.top_services.1.purchases_count', 1)
        ->assertJsonPath('data.top_trainers', [
            ['trainer_id' => $busy->id, 'name' => $busy->name, 'reservations_count' => 2],
            ['trainer_id' => $idle->id, 'name' => $idle->name, 'reservations_count' => 1],
        ]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?from=2026-10-01&to=2026-10-10&limit=1")
        ->assertOk()
        ->assertJsonCount(1, 'data.top_services')
        ->assertJsonCount(1, 'data.top_trainers');
});

test('rejects invalid ranges', function (string $query): void {
    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?{$query}")
        ->assertUnprocessable();
})->with([
    'bad date' => ['from=10-01-2026'],
    'to before from' => ['from=2026-10-10&to=2026-10-01'],
    'limit too large' => ['limit=50'],
]);
