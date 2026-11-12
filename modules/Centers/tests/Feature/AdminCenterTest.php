<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Events\CenterCreated;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
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

test('sorts centers by the requested column and direction', function (): void {
    $alpha = CenterFactory::new()->create(['name' => 'Alpha stable', 'points' => 50]);
    $zulu = CenterFactory::new()->create(['name' => 'Zulu stable', 'points' => 10]);

    $this->actingAs($this->admin, 'system_user')->getJson($this->base)
        ->assertOk()->assertJsonPath('data.0.id', $zulu->id);

    $this->actingAs($this->admin, 'system_user')->getJson("{$this->base}?sort=name")
        ->assertOk()->assertJsonPath('data.0.id', $alpha->id)->assertJsonPath('data.2.id', $zulu->id);

    $this->actingAs($this->admin, 'system_user')->getJson("{$this->base}?sort=-name")
        ->assertOk()->assertJsonPath('data.0.id', $zulu->id)->assertJsonPath('data.2.id', $alpha->id);

    $this->actingAs($this->admin, 'system_user')->getJson("{$this->base}?sort=-points")
        ->assertOk()->assertJsonPath('data.0.id', $alpha->id);
});

test('rejects an unknown sort', function (): void {
    $this->actingAs($this->admin, 'system_user')->getJson("{$this->base}?sort=email")
        ->assertUnprocessable()->assertJsonValidationErrors('sort');
});

test('shows a center', function (): void {
    $this->actingAs($this->admin, 'system_user')->getJson("{$this->base}/{$this->center->id}")
        ->assertOk()->assertJsonPath('data.id', $this->center->id);
});

test('creates a center with a primary owner and media', function (): void {
    Event::fake([CenterCreated::class]);
    $city = CityFactory::new()->create();
    $logo = app(TemporaryMediaService::class)
        ->createTemporary(UploadedFile::fake()->image('logo.png'), $this->admin)->uuid;

    $response = $this->actingAs($this->admin, 'system_user')->postJson($this->base, [
        'name' => 'New Stable',
        'city_id' => $city->id,
        'contact_phone' => '0555',
        'primary_user' => ['name' => 'Owner', 'phone' => '0501112233', 'password' => 'password123'],
        'logo' => $logo,
    ])->assertCreated()->assertJsonPath('data.name', 'New Stable')
        ->assertJsonPath('data.status', 'invisible');

    $center = Center::findOrFail($response->json('data.id'));

    expect($center->slug)->toBe('new-stable')
        ->and($center->media()->where('collection_name', 'logo')->count())->toBe(1);

    $owner = $center->users()->wherePivot('is_primary', true)->sole();

    Event::assertDispatched(CenterCreated::class, fn (CenterCreated $event): bool => $event->center->is($center) && $event->owner->is($owner));

    expect($owner->phone)->toBe('0501112233')
        ->and($owner->name)->toBe('Owner')
        ->and($owner->phone_verified_at)->not->toBeNull()
        ->and(Hash::check('password123', $owner->password))->toBeTrue();
});

test('the primary user created with the center sees it in their centers', function (): void {
    $this->actingAs($this->admin, 'system_user')->postJson($this->base, [
        'name' => 'Owned',
        'city_id' => CityFactory::new()->create()->id,
        'contact_phone' => '0555',
        'primary_user' => ['name' => 'Owner', 'phone' => '0501112233', 'password' => 'password123'],
    ])->assertCreated();

    $owner = User::query()->where('phone', '0501112233')->sole();

    $this->actingAs($owner, 'center_user')->getJson('/api/v1/centers')
        ->assertOk()->assertJsonPath('data.0.name', 'Owned');
});

test('create requires the primary user and a free phone, and leaves nothing behind on failure', function (): void {
    $this->actingAs($this->admin, 'system_user');
    $city = CityFactory::new()->create();
    $base = ['name' => 'X', 'city_id' => $city->id, 'contact_phone' => '0555'];

    $this->postJson($this->base, $base)->assertJsonValidationErrors('primary_user');
    $this->postJson($this->base, [...$base, 'primary_user' => ['name' => 'O']])
        ->assertJsonValidationErrors(['primary_user.phone', 'primary_user.password']);

    $taken = UserFactory::new()->create();
    $this->postJson($this->base, [...$base, 'primary_user' => ['name' => 'O', 'phone' => $taken->phone, 'password' => 'password123']])
        ->assertJsonValidationErrors('primary_user.phone');

    expect(Center::query()->where('name', 'X')->exists())->toBeFalse();
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

test('deleted centers are listed only when the trashed filter asks for them', function (): void {
    $deleted = CenterFactory::new()->create(['name' => 'Closed stable']);
    $deleted->delete();

    $this->actingAs($this->admin, 'system_user');

    $this->getJson($this->base)
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->center->id)
        ->assertJsonPath('data.0.deleted_at', null);

    $this->getJson("{$this->base}?trashed=only")
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $deleted->id)
        ->assertJsonPath('data.0.deleted_at', $deleted->deleted_at->toIso8601String());

    $this->getJson("{$this->base}?trashed=with")->assertOk()->assertJsonCount(2, 'data');

    $this->getJson("{$this->base}?trashed=yes")
        ->assertUnprocessable()->assertJsonValidationErrors(['trashed']);
});

test('shows a deleted center but does not update it', function (): void {
    $this->center->delete();

    $this->actingAs($this->admin, 'system_user');

    $this->getJson("{$this->base}/{$this->center->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $this->center->id)
        ->assertJsonPath('data.deleted_at', $this->center->deleted_at->toIso8601String());

    $this->patchJson("{$this->base}/{$this->center->id}", ['name' => 'X'])->assertNotFound();
});

test('showing a deleted center still needs the view permission', function (): void {
    $this->center->delete();

    $this->actingAs(SystemUser::factory()->create(), 'system_user')
        ->getJson("{$this->base}/{$this->center->id}")->assertForbidden();
});

test('updates a center\'s points', function (): void {
    $this->actingAs($this->admin, 'system_user')
        ->patchJson("{$this->base}/{$this->center->id}", ['points' => 250])
        ->assertOk()->assertJsonPath('data.points', 250);

    expect($this->center->refresh()->points)->toBe(250);
});

test('points must be a non-negative integer', function (mixed $points): void {
    $this->actingAs($this->admin, 'system_user')
        ->patchJson("{$this->base}/{$this->center->id}", ['points' => $points])
        ->assertUnprocessable()->assertJsonValidationErrors(['points']);

    expect($this->center->refresh()->points)->toBe(0);
})->with([-1, 1.5, 'many', null]);

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
    $this->actingAs($limited, 'system_user')
        ->deleteJson("{$this->base}/{$this->center->id}/blocked-clients/{$client->id}")->assertForbidden();

    $this->actingAs($this->admin, 'system_user')
        ->deleteJson("{$this->base}/{$this->center->id}/blocked-clients/{$client->id}")
        ->assertNoContent();
    $this->actingAs($this->admin, 'system_user')
        ->getJson("{$this->base}/{$this->center->id}/blocked-clients")
        ->assertOk()->assertJsonCount(0, 'data');
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

test('admin sets the domain and enables the custom site', function (): void {
    $this->actingAs($this->admin, 'system_user')
        ->patchJson("{$this->base}/{$this->center->id}", [
            'domain' => ' Club.Example.com ',
            'custom_site_enabled' => true,
        ])->assertOk()
        ->assertJsonPath('data.domain', 'club.example.com')
        ->assertJsonPath('data.custom_site_enabled', true);

    $this->patchJson("{$this->base}/{$this->center->id}", ['domain' => 'club.example.com'])->assertOk();

    $this->patchJson("{$this->base}/{$this->center->id}", ['domain' => 'not a domain'])
        ->assertJsonValidationErrors('domain');
});

test('domain must be unique across centers', function (): void {
    CenterFactory::new()->create(['domain' => 'taken.example.com']);

    $this->actingAs($this->admin, 'system_user')
        ->patchJson("{$this->base}/{$this->center->id}", ['domain' => 'taken.example.com'])
        ->assertJsonValidationErrors('domain');
});

test('admin updates social links and site theme through their own endpoints', function (): void {
    $this->actingAs($this->admin, 'system_user');

    $this->patchJson("{$this->base}/{$this->center->id}/social-links", ['social_links' => ['instagram' => 'https://instagram.com/khyyal']])
        ->assertOk()->assertJsonPath('data.social_links.instagram', 'https://instagram.com/khyyal');
    $this->putJson("{$this->base}/{$this->center->id}/site-theme", ['site_theme' => ['primary' => '#0f766e']])
        ->assertOk()->assertJsonPath('data.site_theme.primary', '#0f766e');
    $this->patchJson("{$this->base}/{$this->center->id}/site-theme", ['site_theme' => ['primary' => 'red']])
        ->assertJsonValidationErrors('site_theme.primary');

    $this->patchJson("{$this->base}/{$this->center->id}", ['social_links' => ['instagram' => 'ignored']])->assertOk();
    expect($this->center->fresh()->social_links)->toBe(['instagram' => 'https://instagram.com/khyyal']);

    $this->actingAs(SystemUser::factory()->create(), 'system_user')
        ->patchJson("{$this->base}/{$this->center->id}/social-links", ['social_links' => null])->assertForbidden();
});

test('creates a center with a normalized email and rejects invalid or duplicate emails', function (): void {
    $this->actingAs($this->admin, 'system_user');
    $city = CityFactory::new()->create();
    $payload = fn (string $email, string $phone): array => [
        'name' => 'Mailed', 'city_id' => $city->id, 'contact_phone' => '0555', 'email' => $email,
        'primary_user' => ['name' => 'Owner', 'phone' => $phone, 'password' => 'password123'],
    ];

    $this->postJson($this->base, $payload('not-an-email', '0501112233'))->assertJsonValidationErrors('email');

    $this->postJson($this->base, $payload('  Info@Center.TEST ', '0501112233'))
        ->assertCreated()->assertJsonPath('data.email', 'info@center.test');

    $this->postJson($this->base, $payload('info@center.test', '0501112244'))->assertJsonValidationErrors('email');
});

test('updates a center email and allows keeping its own but not another center\'s', function (): void {
    $other = CenterFactory::new()->create(['email' => 'taken@center.test']);
    $this->center->update(['email' => 'mine@center.test']);

    $this->actingAs($this->admin, 'system_user');
    $url = "{$this->base}/{$this->center->id}";

    $this->patchJson($url, ['email' => 'mine@center.test'])->assertOk();
    $this->patchJson($url, ['email' => 'taken@center.test'])->assertJsonValidationErrors('email');
    $this->patchJson($url, ['email' => 'new@center.test'])->assertOk()->assertJsonPath('data.email', 'new@center.test');
    $this->patchJson($url, ['email' => null])->assertOk()->assertJsonPath('data.email', null);
});
