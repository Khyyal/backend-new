<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Models\ServicePackage;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->user = UserFactory::new()->create();
    $this->center->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $this->service = BookingFixtures::riding($this->center);
    $this->url = "/api/v1/centers/{$this->center->id}/packages";

    $this->servicePackage = ServicePackage::query()->create([
        'service_id' => $this->service->id,
        'price_option_id' => $this->service->priceOptions()->first()->id,
        'count' => 4,
        'price' => 350,
        'duration' => 2,
    ]);

    $this->package = fn (array $attributes = [], array $client = []) => ClientPackageFactory::new()
        ->forServicePackage($this->servicePackage)
        ->create([
            'client_id' => ClientFactory::new()->create($client)->id,
            ...$attributes,
        ]);
});

test('guests and users of other centers are rejected', function (): void {
    $this->getJson($this->url)->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson($this->url)
        ->assertForbidden();
});

test('lists only the center packages, never pending ones', function (): void {
    $kept = ($this->package)();
    ($this->package)(['status' => ClientPackageStatus::Pending]);
    ClientPackageFactory::new()->create();

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $kept->id)
        ->assertJsonPath('data.0.client.id', $kept->client_id);
});

test('filters by active and not_active', function (): void {
    $active = ($this->package)();
    $expired = ($this->package)(['status' => ClientPackageStatus::Expired]);
    $exhausted = ($this->package)(['status' => ClientPackageStatus::Exhausted]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?active=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?not_active=1")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['id' => $expired->id])
        ->assertJsonFragment(['id' => $exhausted->id]);
});

test('searches by client name or phone number', function (): void {
    $sara = ($this->package)([], ['first_name' => 'Sara', 'last_name' => 'Belhadj', 'phone_number' => '0550111222']);
    ($this->package)([], ['first_name' => 'Omar', 'last_name' => 'Kaci', 'phone_number' => '0661333444']);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?q=Sara Bel")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $sara->id);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?q=0550111")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $sara->id);
});

test('filters by purchase date range and service', function (): void {
    $old = ($this->package)(['created_at' => '2026-09-01 10:00:00']);
    $recent = ($this->package)(['created_at' => '2026-10-15 10:00:00']);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?from=2026-10-01&to=2026-10-20")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $recent->id);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?service_id={$this->service->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?service_id=999999")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?from=2026-10-20&to=2026-10-01")
        ->assertUnprocessable();
});

test('counts active and not active packages, ignoring pending ones', function (): void {
    ($this->package)();
    ($this->package)();
    ($this->package)(['status' => ClientPackageStatus::Expired]);
    ($this->package)(['status' => ClientPackageStatus::Cancelled]);
    ($this->package)(['status' => ClientPackageStatus::Exhausted]);
    ($this->package)(['status' => ClientPackageStatus::Pending]);
    ClientPackageFactory::new()->create();

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/counts")
        ->assertOk()
        ->assertExactJson(['data' => ['active' => 2, 'not_active' => 3]]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/counts?service_id=999999")
        ->assertOk()
        ->assertExactJson(['data' => ['active' => 0, 'not_active' => 0]]);

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson("{$this->url}/counts")
        ->assertForbidden();
});
