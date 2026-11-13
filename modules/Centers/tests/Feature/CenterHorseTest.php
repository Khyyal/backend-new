<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\HorseFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Models\Horse;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Models\SystemUser;
use Modules\Support\Services\Media\TemporaryMediaService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->center = CenterFactory::new()->create();
    $this->user = UserFactory::new()->create();
    $this->center->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageHorses]);
    $this->url = "/api/v1/centers/{$this->center->id}/horses";
    $this->tempMedia = fn (string $name = 'a.png') => app(TemporaryMediaService::class)
        ->createTemporary(UploadedFile::fake()->image($name), $this->user)->uuid;
});

test('guests and users without horses.manage are rejected', function (): void {
    $this->postJson($this->url, ['name' => 'Najm', 'room' => 1])->assertUnauthorized();

    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageBlockedClients]);

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ['name' => 'Najm', 'room' => 1])->assertForbidden();
    $this->getJson($this->url)->assertForbidden();
});

test('creates a center-owned horse with an image', function (): void {
    $image = ($this->tempMedia)();

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ['name' => 'Najm', 'room' => 12, 'image' => $image])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Najm')
        ->assertJsonPath('data.room', 12)
        ->assertJsonPath('data.owner_type', 'center')
        ->assertJsonPath('data.client', null)
        ->assertJsonPath('data.image.id', $image);

    expect(Horse::sole()->center_id)->toBe($this->center->id);
});

test('creating a client-owned horse attaches the client to the center once', function (): void {
    $client = ClientFactory::new()->create();

    $this->actingAs($this->user, 'center_user');
    $this->postJson($this->url, ['name' => 'Najm', 'room' => 1, 'client_id' => $client->id])
        ->assertCreated()
        ->assertJsonPath('data.owner_type', 'client')
        ->assertJsonPath('data.client.id', $client->id);
    $this->postJson($this->url, ['name' => 'Barq', 'room' => 1, 'client_id' => $client->id])->assertCreated();

    expect($this->center->clients()->pluck('clients.id')->all())->toBe([$client->id]);
});

test('validates the body', function (array $body, string $error): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$error]);
})->with([
    'missing name' => [['room' => 1], 'name'],
    'missing room' => [['name' => 'Najm'], 'room'],
    'room below 1' => [['name' => 'Najm', 'room' => 0], 'room'],
    'unknown client' => [['name' => 'Najm', 'room' => 1, 'client_id' => 999999], 'client_id'],
]);

test('rejects a deleted client', function (): void {
    $client = ClientFactory::new()->create();
    $client->delete();

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ['name' => 'Najm', 'room' => 1, 'client_id' => $client->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['client_id']);
});

test('update switches the owner and keeps or removes the image', function (): void {
    $client = ClientFactory::new()->create();
    $image = ($this->tempMedia)();
    $this->actingAs($this->user, 'center_user');
    $id = $this->postJson($this->url, ['name' => 'Najm', 'room' => 1, 'image' => $image])->json('data.id');

    $this->putJson("{$this->url}/{$id}", ['name' => 'Najm II', 'room' => 3, 'client_id' => $client->id])
        ->assertOk()
        ->assertJsonPath('data.name', 'Najm II')
        ->assertJsonPath('data.room', 3)
        ->assertJsonPath('data.owner_type', 'client')
        ->assertJsonPath('data.image.id', $image);
    expect($this->center->clients()->whereKey($client->id)->exists())->toBeTrue();

    $this->putJson("{$this->url}/{$id}", ['name' => 'Najm II', 'room' => 3, 'image' => null])
        ->assertOk()
        ->assertJsonPath('data.owner_type', 'center')
        ->assertJsonPath('data.image', null);
});

test('lists with filters, shows and deletes', function (): void {
    $client = ClientFactory::new()->create();
    HorseFactory::new()->for($this->center)->create(['name' => 'Najm']);
    HorseFactory::new()->for($this->center)->create(['name' => 'Barq', 'client_id' => $client->id]);
    HorseFactory::new()->create();

    $this->actingAs($this->user, 'center_user');
    $this->getJson($this->url)->assertOk()->assertJsonCount(2, 'data');
    $this->getJson("{$this->url}?owner=center")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Najm');
    $this->getJson("{$this->url}?owner=client")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Barq');
    $this->getJson("{$this->url}?client_id={$client->id}")->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->url}?q=naj")->assertOk()->assertJsonCount(1, 'data');

    $id = Horse::query()->where('name', 'Najm')->value('id');
    $this->getJson("{$this->url}/{$id}")->assertOk()->assertJsonPath('data.id', $id);
    $this->deleteJson("{$this->url}/{$id}")->assertNoContent();
    $this->getJson("{$this->url}/{$id}")->assertNotFound();
    expect(Horse::withTrashed()->find($id)->trashed())->toBeTrue();
});

test('cannot access a horse of another center', function (): void {
    $horse = HorseFactory::new()->create();

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/{$horse->id}")->assertNotFound();
    $this->deleteJson("{$this->url}/{$horse->id}")->assertNotFound();
});

test('a system user manages horses through the admin routes', function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $admin = SystemUser::factory()->superAdmin()->create();

    $this->actingAs($admin, 'system_user')
        ->postJson("/api/v1/admin/centers/{$this->center->id}/horses", ['name' => 'Najm', 'room' => 4])
        ->assertCreated()
        ->assertJsonPath('data.owner_type', 'center');

    $this->actingAs(SystemUser::factory()->create(), 'system_user')
        ->getJson("/api/v1/admin/centers/{$this->center->id}/horses")
        ->assertForbidden();
});
