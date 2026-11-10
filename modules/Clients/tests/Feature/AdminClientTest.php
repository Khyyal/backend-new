<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Support\Database\Factories\CityFactory;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\SystemPermission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $this->admin = SystemUserFactory::new()->superAdmin()->create();
});

test('guests are unauthenticated and users without permission are forbidden', function (): void {
    $client = ClientFactory::new()->create();

    $this->getJson('/api/v1/admin/clients')->assertUnauthorized();

    $this->actingAs(SystemUserFactory::new()->create(), 'system_user');
    $this->getJson('/api/v1/admin/clients')->assertForbidden();
    $this->getJson("/api/v1/admin/clients/{$client->id}")->assertForbidden();
});

test('admin lists clients across centers with search and city filter', function (): void {
    $city = CityFactory::new()->create();
    ClientFactory::new()->create(['first_name' => 'Aisha', 'phone_number' => '500000001', 'city_id' => $city->id]);
    ClientFactory::new()->create(['first_name' => 'Omar', 'phone_number' => '500000002']);

    $this->actingAs($this->admin, 'system_user');

    $this->getJson('/api/v1/admin/clients')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/admin/clients?search=aish')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.first_name', 'Aisha');
    $this->getJson('/api/v1/admin/clients?search=500000002')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/admin/clients?city_id={$city->id}")->assertOk()->assertJsonCount(1, 'data');
});

test('admin shows a client with activity counts and soft-deleted clients are hidden', function (): void {
    $client = ClientFactory::new()->create();
    $deleted = ClientFactory::new()->create();
    $deleted->delete();

    $this->actingAs($this->admin, 'system_user');

    $this->getJson("/api/v1/admin/clients/{$client->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $client->id)
        ->assertJsonPath('data.reservations_count', 0)
        ->assertJsonPath('data.centers_count', 0);
    $this->getJson("/api/v1/admin/clients/{$deleted->id}")->assertNotFound();
    $this->getJson('/api/v1/admin/clients')->assertJsonCount(1, 'data');
});

test('the view permissions open only their own ability', function (): void {
    $client = ClientFactory::new()->create();
    $viewer = SystemUserFactory::new()->withPermissions([SystemPermission::ViewClient])->create();

    $this->actingAs($viewer, 'system_user');
    $this->getJson("/api/v1/admin/clients/{$client->id}")->assertOk();
    $this->getJson('/api/v1/admin/clients')->assertForbidden();
});

test('search finds clients by full name or phone number', function (): void {
    ClientFactory::new()->create(['first_name' => 'Aisha', 'last_name' => 'Noor', 'phone_number' => '500000001']);
    ClientFactory::new()->create(['first_name' => 'Aisha', 'last_name' => 'Salem', 'phone_number' => '500000002']);

    $this->getJson('/api/v1/admin/clients/search?q=aisha')->assertUnauthorized();

    $this->actingAs($this->admin, 'system_user');

    $this->getJson('/api/v1/admin/clients/search?q=Aisha Noor')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.full_name', 'Aisha Noor');
    $this->getJson('/api/v1/admin/clients/search?q=noor aisha')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/admin/clients/search?q=500000002')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.phone_number', '500000002');
    $this->getJson('/api/v1/admin/clients/search?q=aisha&limit=1')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/admin/clients/search?q=a')->assertUnprocessable();
    $this->getJson('/api/v1/admin/clients?search=Aisha Salem')->assertOk()->assertJsonCount(1, 'data');
});

test('admin sorts clients by name or registration date and rejects unknown sorts', function (): void {
    $omar = ClientFactory::new()->create(['first_name' => 'Omar', 'last_name' => 'Ali', 'created_at' => '2026-01-10 10:00:00']);
    $aisha = ClientFactory::new()->create(['first_name' => 'Aisha', 'last_name' => 'Noor', 'created_at' => '2026-03-10 10:00:00']);

    $this->actingAs($this->admin, 'system_user');
    $ids = fn (string $sort): array => collect($this->getJson("/api/v1/admin/clients?sort={$sort}")->assertOk()->json('data'))->pluck('id')->all();

    expect($ids('name'))->toBe([$aisha->id, $omar->id])
        ->and($ids('-name'))->toBe([$omar->id, $aisha->id])
        ->and($ids('created_at'))->toBe([$omar->id, $aisha->id]);

    $this->getJson('/api/v1/admin/clients?sort=phone_number')->assertUnprocessable()->assertJsonValidationErrors('sort');
});
