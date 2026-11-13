<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\HorseFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Database\Factories\PurchaseItemFactory;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Purchase;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Database\Factories\StayInvoiceFactory;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Database\Factories\StablingFactory;
use Modules\Services\Database\Factories\TrainerFactory;
use Modules\Services\Enums\ServiceType;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-12-10 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->user = UserFactory::new()->create();
    $this->center->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations]);
    $this->url = "/api/v1/centers/{$this->center->id}/horse-stays";

    $this->stabling = BookingFixtures::service($this->center, StablingFactory::new()->create(), ServiceType::Stabling);
    $this->owner = ClientFactory::new()->create(['first_name' => 'Noura', 'last_name' => 'Qahtani', 'phone_number' => '+966500000003']);
    $this->stay = fn ($horse, array $attributes = []) => ReservationFactory::new()->forService($this->stabling)->create([
        'client_id' => $horse->client_id ?? $this->owner->id,
        'horse_id' => $horse->id,
        'status' => ReservationStatus::Confirmed,
        'date' => '2026-12-01',
        'end_date' => '2026-12-31',
        ...$attributes,
    ]);

    // A: active stay; B: only a future stay; C: completed stay; D: no stay.
    $this->alpha = HorseFactory::new()->for($this->center)->create(['name' => 'Alpha', 'room' => 1, 'client_id' => $this->owner->id]);
    $this->bravo = HorseFactory::new()->for($this->center)->forClient()->create(['name' => 'Bravo']);
    $this->charlie = HorseFactory::new()->for($this->center)->forClient()->create(['name' => 'Charlie']);
    $this->delta = HorseFactory::new()->for($this->center)->create(['name' => 'Delta']);

    $this->activeStay = ($this->stay)($this->alpha);
    ($this->stay)($this->bravo, ['date' => '2027-01-01', 'end_date' => '2027-01-31']);
    ($this->stay)($this->charlie, ['status' => ReservationStatus::Completed]);

    $otherCenter = CenterFactory::new()->create();
    $otherStabling = BookingFixtures::service($otherCenter, StablingFactory::new()->create(), ServiceType::Stabling);
    $otherHorse = HorseFactory::new()->for($otherCenter)->forClient()->create(['name' => 'Aaron']);
    ReservationFactory::new()->forService($otherStabling)->create(['horse_id' => $otherHorse->id, 'client_id' => $otherHorse->client_id, 'status' => ReservationStatus::Confirmed, 'date' => '2026-12-01']);
});

test('all lists active stays and horses without one, by horse name', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.type', 'stay')
        ->assertJsonPath('data.0.id', $this->activeStay->id)
        ->assertJsonPath('data.1.type', 'horse')
        ->assertJsonPath('data.1.name', 'Bravo')
        ->assertJsonPath('data.2.name', 'Charlie')
        ->assertJsonPath('data.3.name', 'Delta')
        ->assertJsonPath('meta.total', 4);
});

test('active and others split the board', function (): void {
    $this->actingAs($this->user, 'center_user');

    $this->getJson("{$this->url}?type=active")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'stay');

    $this->getJson("{$this->url}?type=others")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.*.type', ['horse', 'horse', 'horse']);

    $this->getJson("{$this->url}?type=nope")->assertUnprocessable()->assertJsonValidationErrors(['type']);
});

test('a stay row carries its horse, service and unpaid operations', function (): void {
    $purchaseOf = fn (PurchaseStatus $status) => Purchase::factory()->create(['status' => $status]);
    PurchaseItemFactory::new()->create(['purchase_id' => $purchaseOf(PurchaseStatus::Pending)->id, 'purchasable_type' => $this->activeStay->getMorphClass(), 'purchasable_id' => $this->activeStay->id]);

    foreach ([PurchaseStatus::Confirmed, PurchaseStatus::Pending] as $status) {
        $invoice = StayInvoiceFactory::new()->create(['reservation_id' => $this->activeStay->id]);
        PurchaseItemFactory::new()->create(['purchase_id' => $purchaseOf($status)->id, 'purchasable_type' => $invoice->getMorphClass(), 'purchasable_id' => $invoice->id]);
    }

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?type=active")
        ->assertOk()
        ->assertJsonPath('data.0', [
            'type' => 'stay',
            'id' => $this->activeStay->id,
            'status' => 'confirmed',
            'end_date' => '2026-12-31',
            'horse' => [
                'id' => $this->alpha->id,
                'name' => 'Alpha',
                'image' => null,
                'room' => 1,
                'client' => ['id' => $this->owner->id, 'first_name' => 'Noura', 'last_name' => 'Qahtani', 'phone_number' => '+966500000003'],
            ],
            'service' => ['id' => $this->stabling->id, 'name' => $this->stabling->getTranslations('name')],
            'unpaid_operations_count' => 2,
        ]);
});

test('a horse row carries its client and trainers', function (): void {
    $trainer = TrainerFactory::new()->create(['center_id' => $this->center->id, 'name' => 'Fahad']);
    $this->delta->trainers()->attach($trainer->id);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?type=others&q=Delta")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.client', null)
        ->assertJsonPath('data.0.trainers', [['id' => $trainer->id, 'name' => 'Fahad']]);
});

test('searches the horse name, client name and phone', function (string $q, array $names): void {
    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?q=".urlencode($q))
        ->assertOk()
        ->assertJsonCount(count($names), 'data');
})->with([
    'horse name' => ['harl', ['Charlie']],
    'client last name' => ['qahtani', ['Alpha']],
    'client phone' => ['500000003', ['Alpha']],
    'every word' => ['noura alpha', ['Alpha']],
    'no match' => ['zzz', []],
]);

test('needs horse or reservation access', function (): void {
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageHorses]);
    $this->actingAs($this->user, 'center_user')->getJson($this->url)->assertOk();

    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ViewRatings]);
    $this->getJson($this->url)->assertForbidden();
});

test('a system user with centers.manage_horses can see the board', function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $admin = SystemUser::factory()->withPermissions([SystemPermission::ManageCenterHorses])->create();

    $this->actingAs($admin, 'system_user')
        ->getJson("/api/v1/admin/centers/{$this->center->id}/horse-stays")
        ->assertOk()
        ->assertJsonCount(4, 'data');
});
