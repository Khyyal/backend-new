<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Models\Center;
use Modules\Support\Database\Factories\CityFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Models\SystemUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function centerOwnedBy(CenterFactory $factory): array
{
    $center = $factory->create(['status' => CenterStatus::VISIBLE]);
    $owner = UserFactory::new()->create();
    $center->users()->attach($owner->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => true,
    ]);

    return [$center, $owner];
}

test('centers are active by default', function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $admin = SystemUser::factory()->superAdmin()->create();

    $id = $this->actingAs($admin, 'system_user')
        ->postJson('/api/v1/admin/centers', ['name' => 'New', 'city_id' => CityFactory::new()->create()->id, 'contact_phone' => '0555'])
        ->assertCreated()
        ->assertJsonPath('data.state', 'active')
        ->json('data.id');

    expect(Center::query()->findOrFail($id)->state)->toBe(CenterState::Active);

    $registered = Center::query()->create(['name' => 'Registered', 'slug' => 'registered']);
    expect($registered->fresh()->state)->toBe(CenterState::Active);
});

test('admins set and filter the state', function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $admin = SystemUser::factory()->superAdmin()->create();
    $center = CenterFactory::new()->create();
    $blocked = CenterFactory::new()->blocked()->create();

    $this->actingAs($admin, 'system_user')
        ->patchJson("/api/v1/admin/centers/{$center->id}", ['state' => 'pending'])
        ->assertOk()
        ->assertJsonPath('data.state', 'pending');

    $this->actingAs($admin, 'system_user')
        ->patchJson("/api/v1/admin/centers/{$center->id}", ['state' => 'closed'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['state']);

    $this->actingAs($admin, 'system_user')
        ->getJson('/api/v1/admin/centers?state=blocked')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $blocked->id);
});

test('inactive centers cannot use center-scoped endpoints but can see their state', function (CenterFactory $factory, string $state): void {
    [$center, $owner] = centerOwnedBy($factory);

    $this->actingAs($owner, 'center_user')
        ->getJson("/api/v1/centers/{$center->id}/ratings")
        ->assertForbidden();

    $this->actingAs($owner, 'center_user')
        ->getJson("/api/v1/centers/{$center->id}")
        ->assertOk()
        ->assertJsonPath('data.state', $state);

    $this->actingAs($owner, 'center_user')
        ->getJson('/api/v1/centers')
        ->assertOk()
        ->assertJsonPath('data.0.state', $state);
})->with([
    'pending' => [fn () => CenterFactory::new()->pending(), 'pending'],
    'blocked' => [fn () => CenterFactory::new()->blocked(), 'blocked'],
]);

test('active centers can use center-scoped endpoints', function (): void {
    [$center, $owner] = centerOwnedBy(CenterFactory::new());

    $this->actingAs($owner, 'center_user')
        ->getJson("/api/v1/centers/{$center->id}/ratings")
        ->assertOk();
});

test('clients only see active centers', function (): void {
    $active = CenterFactory::new()->create(['status' => CenterStatus::VISIBLE]);
    CenterFactory::new()->pending()->create(['status' => CenterStatus::VISIBLE]);
    CenterFactory::new()->blocked()->create(['status' => CenterStatus::VISIBLE]);

    $this->getJson('/api/v1/clients/centers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id);
});
