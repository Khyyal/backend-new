<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Support\Database\Factories\CityFactory;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\City;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $this->admin = SystemUserFactory::new()->superAdmin()->create();
    $this->base = '/api/v1/admin/cities';
    $this->payload = ['name' => ['en' => 'Riyadh', 'ar' => 'الرياض'], 'lat' => 24.7136, 'lng' => 46.6753, 'radius' => 40];
});

test('guests are unauthenticated and users without permission are forbidden', function (): void {
    $this->getJson($this->base)->assertUnauthorized();

    $this->actingAs(SystemUserFactory::new()->create(), 'system_user');
    $this->getJson($this->base)->assertForbidden();
    $this->postJson($this->base, $this->payload)->assertForbidden();
});

test('a permission only opens its own ability', function (): void {
    $viewer = SystemUserFactory::new()->withPermissions([SystemPermission::ViewAnyCities])->create();

    $this->actingAs($viewer, 'system_user')->getJson($this->base)->assertOk();
    $this->postJson($this->base, $this->payload)->assertForbidden();
});

test('admin creates, shows, lists, updates and deletes a city', function (): void {
    $this->actingAs($this->admin, 'system_user');

    $id = $this->postJson($this->base, $this->payload)
        ->assertCreated()
        ->assertJsonPath('data.name.ar', 'الرياض')
        ->assertJsonPath('data.radius', 40)
        ->json('data.id');

    $this->getJson("{$this->base}/{$id}")->assertOk()->assertJsonPath('data.centers_count', 0);
    $this->getJson("{$this->base}?search=riy")->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->base}?search=zzz")->assertOk()->assertJsonCount(0, 'data');

    $this->patchJson("{$this->base}/{$id}", ['radius' => 55])->assertOk()->assertJsonPath('data.radius', 55)->assertJsonPath('data.name.en', 'Riyadh');
    $this->putJson("{$this->base}/{$id}", ['name' => ['en' => 'Riyadh City', 'ar' => 'مدينة الرياض']])->assertOk()->assertJsonPath('data.name.en', 'Riyadh City');

    $this->deleteJson("{$this->base}/{$id}")->assertNoContent();
    expect(City::query()->count())->toBe(0);
    $this->getJson("{$this->base}/{$id}")->assertNotFound();
});

test('create validates the translations, the point and the radius', function (): void {
    $this->actingAs($this->admin, 'system_user')
        ->postJson($this->base, ['name' => ['en' => 'Only English'], 'lat' => 100, 'lng' => 200, 'radius' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name.ar', 'lat', 'lng', 'radius']);
});

test('a city with centers cannot be deleted', function (): void {
    $city = CityFactory::new()->create();
    CenterFactory::new()->create(['city_id' => $city->id]);

    $this->actingAs($this->admin, 'system_user')
        ->getJson("{$this->base}/{$city->id}")->assertJsonPath('data.centers_count', 1);

    $this->deleteJson("{$this->base}/{$city->id}")->assertUnprocessable();
    expect(City::query()->whereKey($city->id)->exists())->toBeTrue();
});
