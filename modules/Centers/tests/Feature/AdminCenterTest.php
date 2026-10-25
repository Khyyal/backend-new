<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Models\Center;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Support\Database\Factories\CityFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;
use Modules\Support\Services\Media\TemporaryMediaService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->seed(SystemRolePermissionSeeder::class);
    $this->admin = SystemUser::factory()->superAdmin()->create();
    $this->center = CenterFactory::new()->create(['name' => 'Old name', 'status' => CenterStatus::VISIBLE]);
    $this->base = '/api/v1/admin/centers';
});

test('guests and center users cannot reach admin center routes', function (): void {
    $this->getJson($this->base)->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson($this->base)->assertUnauthorized();
});

test('system user without permission is forbidden', function (): void {
    $user = SystemUser::factory()->create();

    $this->actingAs($user, 'system_user')->getJson($this->base)->assertForbidden();
    $this->actingAs($user, 'system_user')->getJson("{$this->base}/{$this->center->id}")->assertForbidden();
    $this->actingAs($user, 'system_user')->postJson($this->base, [])->assertForbidden();
    $this->actingAs($user, 'system_user')->patchJson("{$this->base}/{$this->center->id}", ['name' => 'X'])->assertForbidden();
    $this->actingAs($user, 'system_user')->deleteJson("{$this->base}/{$this->center->id}")->assertForbidden();
});

test('lists centers with filters', function (): void {
    CenterFactory::new()->create(['name' => 'Hidden stable', 'status' => CenterStatus::INVISIBLE]);

    $this->actingAs($this->admin, 'system_user')->getJson($this->base)
        ->assertOk()->assertJsonCount(2, 'data');

    $this->actingAs($this->admin, 'system_user')->getJson("{$this->base}?status=invisible")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Hidden stable');

    $this->actingAs($this->admin, 'system_user')->getJson("{$this->base}?search=Old")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->center->id);
});

test('shows a center', function (): void {
    $this->actingAs($this->admin, 'system_user')->getJson("{$this->base}/{$this->center->id}")
        ->assertOk()->assertJsonPath('data.id', $this->center->id);
});

test('creates a center with a primary owner and media', function (): void {
    $city = CityFactory::new()->create();
    $owner = UserFactory::new()->create();
    $logo = app(TemporaryMediaService::class)
        ->createTemporary(UploadedFile::fake()->image('logo.png'), $this->admin)->uuid;

    $response = $this->actingAs($this->admin, 'system_user')->postJson($this->base, [
        'name' => 'New Stable',
        'city_id' => $city->id,
        'contact_phone' => '0555',
        'primary_user_id' => $owner->id,
        'logo' => $logo,
    ])->assertCreated()->assertJsonPath('data.name', 'New Stable')
        ->assertJsonPath('data.status', 'invisible');

    $center = Center::findOrFail($response->json('data.id'));

    expect($center->slug)->toBe('new-stable')
        ->and($center->users()->wherePivot('is_primary', true)->pluck('center_users.id')->all())->toBe([$owner->id])
        ->and($center->media()->where('collection_name', 'logo')->count())->toBe(1);
});

test('create validates required fields', function (): void {
    $this->actingAs($this->admin, 'system_user')->postJson($this->base, [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'city_id', 'contact_phone']);
});

test('updates a center including status and city', function (): void {
    $city = CityFactory::new()->create();

    $this->actingAs($this->admin, 'system_user')
        ->patchJson("{$this->base}/{$this->center->id}", [
            'name' => 'New name',
            'status' => 'invisible',
            'city_id' => $city->id,
        ])->assertOk()->assertJsonPath('data.name', 'New name');

    $this->center->refresh();

    expect($this->center->status)->toBe(CenterStatus::INVISIBLE)
        ->and($this->center->city_id)->toBe($city->id);
});

test('deletes a center', function (): void {
    $this->actingAs($this->admin, 'system_user')
        ->deleteJson("{$this->base}/{$this->center->id}")->assertNoContent();

    expect(Center::find($this->center->id))->toBeNull()
        ->and(Center::withTrashed()->find($this->center->id))->not->toBeNull();
});

test('permissions are enforced per ability', function (): void {
    $viewer = SystemUser::factory()
        ->withPermissions([SystemPermission::ViewAnyCenters, SystemPermission::ViewCenter])
        ->create();

    $this->actingAs($viewer, 'system_user')->getJson($this->base)->assertOk();
    $this->actingAs($viewer, 'system_user')->getJson("{$this->base}/{$this->center->id}")->assertOk();
    $this->actingAs($viewer, 'system_user')->deleteJson("{$this->base}/{$this->center->id}")->assertForbidden();
});

test('lists ratings and manages blocked clients', function (): void {
    $client = ClientFactory::new()->create();

    $this->actingAs($this->admin, 'system_user')
        ->getJson("{$this->base}/{$this->center->id}/ratings")
        ->assertOk()->assertJsonPath('summary.count', 0);

    $this->actingAs($this->admin, 'system_user')
        ->postJson("{$this->base}/{$this->center->id}/blocked-clients", ['client_id' => $client->id, 'reason' => 'No-shows'])
        ->assertCreated()->assertJsonPath('data.client_id', $client->id);

    $this->actingAs($this->admin, 'system_user')
        ->getJson("{$this->base}/{$this->center->id}/blocked-clients")
        ->assertOk()->assertJsonCount(1, 'data');

    $limited = SystemUser::factory()->withPermissions([SystemPermission::ViewCenterRatings])->create();
    $this->actingAs($limited, 'system_user')
        ->getJson("{$this->base}/{$this->center->id}/ratings")->assertOk();
    $this->actingAs($limited, 'system_user')
        ->getJson("{$this->base}/{$this->center->id}/blocked-clients")->assertForbidden();
});

test('manages a center\'s services', function (): void {
    $url = "{$this->base}/{$this->center->id}/service-blocks";

    $this->actingAs($this->admin, 'system_user')->postJson($url, [
        'reason' => 'Maintenance',
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
        'time_scope' => 'all_day',
    ])->assertCreated();

    $this->actingAs($this->admin, 'system_user')->getJson($url)
        ->assertOk()->assertJsonCount(1, 'data');

    $this->actingAs($this->admin, 'system_user')
        ->getJson("{$this->base}/{$this->center->id}/service-types/counts")->assertOk();

    $this->actingAs($this->admin, 'system_user')
        ->getJson("{$this->base}/{$this->center->id}/events")->assertOk();

    $noPermission = SystemUser::factory()->create();
    $this->actingAs($noPermission, 'system_user')->getJson($url)->assertForbidden();
    $this->actingAs($noPermission, 'system_user')->postJson($url, [])->assertForbidden();
});

test('center users keep their scoped access to the center portal', function (): void {
    $outsider = UserFactory::new()->create();

    $this->actingAs($outsider, 'center_user')
        ->getJson("/api/v1/centers/{$this->center->id}/ratings")->assertForbidden();
});
