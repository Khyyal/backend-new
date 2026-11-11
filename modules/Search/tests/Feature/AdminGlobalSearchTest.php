<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Models\Center;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Reservations\Database\Factories\ClientMembershipFactory;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\SystemPermission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $this->admin = SystemUserFactory::new()->superAdmin()->create();
});

test('guests are unauthenticated and the term is validated', function (): void {
    $this->getJson('/api/v1/admin/search?q=aisha')->assertUnauthorized();

    $this->actingAs($this->admin, 'system_user');
    $this->getJson('/api/v1/admin/search')->assertUnprocessable()->assertJsonValidationErrors('q');
    $this->getJson('/api/v1/admin/search?q=a')->assertUnprocessable()->assertJsonValidationErrors('q');
    $this->getJson('/api/v1/admin/search?q=aisha&limit=11')->assertUnprocessable()->assertJsonValidationErrors('limit');
});

test('a client name matches every group that belongs to the client', function (): void {
    $client = ClientFactory::new()->create(['first_name' => 'Aisha', 'last_name' => 'Zahrani', 'phone_number' => '500000001']);
    ClientFactory::new()->create(['first_name' => 'Omar', 'last_name' => 'Ali']);
    $center = Center::factory()->create();
    $service = ServiceFactory::new()->create(['center_id' => $center->id]);

    $reservation = ReservationFactory::new()->forService($service)->create(['client_id' => $client->id]);
    $purchase = PurchaseFactory::new()->create([
        'buyer_id' => $client->id,
        'merchant_type' => $center->getMorphClass(),
        'merchant_id' => $center->id,
    ]);
    $package = ClientPackageFactory::new()->create(['client_id' => $client->id]);
    ClientPackageFactory::new()->create(['client_id' => $client->id, 'status' => ClientPackageStatus::Pending]);
    $membership = ClientMembershipFactory::new()->create(['client_id' => $client->id]);

    $this->actingAs($this->admin, 'system_user');

    $response = $this->getJson('/api/v1/admin/search?q=aisha zahr')->assertOk();

    expect(array_keys($response->json('data')))->toBe([
        'centers', 'clients', 'reservations', 'purchases', 'client_packages', 'client_memberships', 'services',
    ]);
    $response
        ->assertJsonCount(0, 'data.centers')
        ->assertJsonCount(1, 'data.clients')
        ->assertJsonPath('data.clients.0.id', $client->id)
        ->assertJsonPath('data.clients.0.title', 'Aisha Zahrani')
        ->assertJsonPath('data.reservations.0.id', $reservation->id)
        ->assertJsonPath('data.reservations.0.center_id', $center->id)
        ->assertJsonPath('data.reservations.0.code', $reservation->code)
        ->assertJsonPath('data.purchases.0.id', $purchase->id)
        ->assertJsonPath('data.purchases.0.center_id', $center->id)
        ->assertJsonCount(1, 'data.client_packages')
        ->assertJsonPath('data.client_packages.0.id', $package->id)
        ->assertJsonPath('data.client_packages.0.center_id', $package->service->center_id)
        ->assertJsonPath('data.client_memberships.0.id', $membership->id);
});

test('a public code matches the reservation and the purchase exactly', function (): void {
    $reservation = ReservationFactory::new()->create(['code' => 'K7M2QX']);
    ReservationFactory::new()->create(['code' => 'K7M2QA']);
    $center = Center::factory()->create();
    $purchase = PurchaseFactory::new()->create([
        'code' => 'P3RZ8N',
        'merchant_type' => $center->getMorphClass(),
        'merchant_id' => $center->id,
    ]);

    $this->actingAs($this->admin, 'system_user');

    $this->getJson('/api/v1/admin/search?q=k7m2qx')
        ->assertOk()
        ->assertJsonCount(1, 'data.reservations')
        ->assertJsonPath('data.reservations.0.id', $reservation->id);

    $this->getJson('/api/v1/admin/search?q=P3RZ8N')
        ->assertOk()
        ->assertJsonCount(1, 'data.purchases')
        ->assertJsonPath('data.purchases.0.id', $purchase->id);
});

test('centers and services match by name and services carry their route kind', function (): void {
    $center = Center::factory()->create(['name' => 'Al Faris Stables']);
    $service = ServiceFactory::new()->create(['center_id' => $center->id, 'name' => ['en' => 'Dressage course', 'ar' => 'دورة ترويض']]);

    $this->actingAs($this->admin, 'system_user');

    $this->getJson('/api/v1/admin/search?q=faris')
        ->assertOk()
        ->assertJsonPath('data.centers.0.id', $center->id)
        ->assertJsonCount(0, 'data.services');

    $this->getJson('/api/v1/admin/search?q=ترويض&locale=en')
        ->assertOk()
        ->assertJsonPath('data.services.0.id', $service->id)
        ->assertJsonPath('data.services.0.title', 'Dressage course')
        ->assertJsonPath('data.services.0.center_id', $center->id)
        ->assertJsonPath('data.services.0.kind', 'training-services')
        ->assertJsonPath('data.services.0.meta.serviceable_id', $service->serviceable_id);
});

test('only the groups the user may see are returned', function (): void {
    ClientFactory::new()->create(['first_name' => 'Aisha']);
    $viewer = SystemUserFactory::new()->withPermissions([SystemPermission::ViewAnyClients])->create();

    $this->actingAs($viewer, 'system_user');

    $response = $this->getJson('/api/v1/admin/search?q=aisha')->assertOk();
    expect(array_keys($response->json('data')))->toBe(['clients']);

    $this->actingAs(SystemUserFactory::new()->create(), 'system_user');
    $this->getJson('/api/v1/admin/search?q=aisha')->assertOk()->assertExactJson(['data' => []]);
});

test('the limit caps each group and wildcards match literally', function (): void {
    ClientFactory::new()->count(4)->create(['first_name' => 'Aisha']);

    $this->actingAs($this->admin, 'system_user');

    $this->getJson('/api/v1/admin/search?q=aisha&limit=2')->assertOk()->assertJsonCount(2, 'data.clients');
    $this->getJson('/api/v1/admin/search?q=%25%25')->assertOk()->assertJsonCount(0, 'data.clients');
});
