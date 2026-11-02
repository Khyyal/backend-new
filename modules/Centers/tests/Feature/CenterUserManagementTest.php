<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Centers\Services\CenterAuthService;
use Modules\Services\Database\Factories\TrainerFactory;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function assignToCenter(Center $center, User $user, bool $isPrimary = false, ActivationStatus $status = ActivationStatus::ACTIVE, ?int $trainerId = null): void
{
    $center->users()->attach($user->id, [
        'status' => $status->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => $isPrimary,
        'trainer_id' => $trainerId,
    ]);
}

beforeEach(function (): void {
    $this->center = CenterFactory::new()->create();
    $this->owner = UserFactory::new()->create();
    assignToCenter($this->center, $this->owner, isPrimary: true);
    $this->url = "/api/v1/centers/{$this->center->id}/users";
    $this->trainer = TrainerFactory::new()->create(['center_id' => $this->center->id]);
});

test('guests, unassigned and non-primary users cannot manage users', function (): void {
    $this->getJson($this->url)->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson($this->url)->assertForbidden();

    $member = UserFactory::new()->create();
    assignToCenter($this->center, $member);

    $this->actingAs($member, 'center_user')
        ->postJson($this->url, ['name' => 'X', 'phone' => '0500000000', 'permissions' => []])
        ->assertForbidden();
});

test('a token scoped to another center is rejected', function (): void {
    $other = CenterFactory::new()->create();
    $token = app(CenterAuthService::class)->issueCenterAccessToken($this->owner, $other);

    $this->withToken($token)->getJson($this->url)->assertForbidden();
});

test('primary user adds a new user with permissions', function (): void {
    $this->actingAs($this->owner, 'center_user')
        ->postJson($this->url, [
            'name' => 'Sara',
            'phone' => '0511111111',
            'trainer_id' => $this->trainer->id,
            'permissions' => ['services.training.manage', 'ratings.view'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Sara')
        ->assertJsonPath('data.trainer.id', $this->trainer->id)
        ->assertJsonPath('data.trainer.name', $this->trainer->name)
        ->assertJsonPath('data.is_primary', false)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.permissions', ['services.training.manage', 'ratings.view']);

    $user = User::query()->where('phone', '0511111111')->firstOrFail();

    expect($user->hasCenterPermission($this->center, CenterUserPermission::ManageTrainingServices))->toBeTrue()
        ->and($user->hasCenterPermission($this->center, CenterUserPermission::ManageReservations))->toBeFalse();
});

test('adding an existing phone assigns that account and keeps its name', function (): void {
    $existing = UserFactory::new()->create(['name' => 'Original', 'phone' => '0522222222']);
    $otherCenter = CenterFactory::new()->create();
    assignToCenter($otherCenter, $existing);

    $this->actingAs($this->owner, 'center_user')
        ->postJson($this->url, ['name' => 'Renamed', 'phone' => '0522222222', 'trainer_id' => $this->trainer->id, 'permissions' => []])
        ->assertCreated()
        ->assertJsonPath('data.id', $existing->id)
        ->assertJsonPath('data.name', 'Original');

    expect($existing->centers()->count())->toBe(2);
});

test('permissions are scoped per center', function (): void {
    $member = UserFactory::new()->create();
    $otherCenter = CenterFactory::new()->create();
    assignToCenter($this->center, $member);
    assignToCenter($otherCenter, $member);

    $member->syncCenterPermissions($this->center, [CenterUserPermission::ManageTrainingServices]);

    expect($member->hasCenterPermission($this->center, CenterUserPermission::ManageTrainingServices))->toBeTrue()
        ->and($member->hasCenterPermission($otherCenter, CenterUserPermission::ManageTrainingServices))->toBeFalse();
});

test('adding a user already in the center or with an unknown permission fails validation', function (): void {
    $this->actingAs($this->owner, 'center_user')
        ->postJson($this->url, ['name' => 'Owner', 'phone' => $this->owner->phone, 'trainer_id' => $this->trainer->id, 'permissions' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);

    $this->actingAs($this->owner, 'center_user')
        ->postJson($this->url, ['name' => 'X', 'phone' => '0533333333', 'trainer_id' => $this->trainer->id, 'permissions' => ['fly']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions.0']);
});

test('the trainer must be a free trainer of the center', function (): void {
    $body = fn (array $extra = []): array => ['name' => 'X', 'phone' => '0544444444', 'permissions' => [], ...$extra];

    $this->actingAs($this->owner, 'center_user')
        ->postJson($this->url, $body())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['trainer_id']);

    $foreignTrainer = TrainerFactory::new()->create();

    $this->actingAs($this->owner, 'center_user')
        ->postJson($this->url, $body(['trainer_id' => $foreignTrainer->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['trainer_id']);

    assignToCenter($this->center, UserFactory::new()->create(), trainerId: $this->trainer->id);

    $this->actingAs($this->owner, 'center_user')
        ->postJson($this->url, $body(['trainer_id' => $this->trainer->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['trainer_id']);
});

test('lists the center users with the primary user first', function (): void {
    $member = UserFactory::new()->create();
    assignToCenter($this->center, $member, trainerId: $this->trainer->id);
    $member->syncCenterPermissions($this->center, [CenterUserPermission::ViewRatings]);
    assignToCenter(CenterFactory::new()->create(), UserFactory::new()->create());

    $this->actingAs($this->owner, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $this->owner->id)
        ->assertJsonPath('data.0.permissions', CenterUserPermission::all())
        ->assertJsonPath('data.0.trainer', null)
        ->assertJsonPath('data.1.id', $member->id)
        ->assertJsonPath('data.1.trainer.id', $this->trainer->id)
        ->assertJsonPath('data.1.permissions', ['ratings.view']);

    $this->actingAs($this->owner, 'center_user')
        ->getJson("{$this->url}/permissions")
        ->assertOk()
        ->assertJsonPath('data', CenterUserPermission::all());
});

test('updates a user name, status and permissions', function (): void {
    $member = UserFactory::new()->create();
    assignToCenter($this->center, $member);
    $member->syncCenterPermissions($this->center, [CenterUserPermission::ViewRatings]);

    $this->actingAs($this->owner, 'center_user')
        ->patchJson("{$this->url}/{$member->id}", [
            'name' => 'Updated',
            'status' => 'inactive',
            'permissions' => ['reservations.manage'],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated')
        ->assertJsonPath('data.status', 'inactive')
        ->assertJsonPath('data.permissions', ['reservations.manage']);
});

test('updates the linked trainer', function (): void {
    $member = UserFactory::new()->create();
    assignToCenter($this->center, $member, trainerId: $this->trainer->id);
    $otherTrainer = TrainerFactory::new()->create(['center_id' => $this->center->id]);
    $takenTrainer = TrainerFactory::new()->create(['center_id' => $this->center->id]);
    assignToCenter($this->center, UserFactory::new()->create(), trainerId: $takenTrainer->id);

    $this->actingAs($this->owner, 'center_user')
        ->patchJson("{$this->url}/{$member->id}", ['trainer_id' => $this->trainer->id])
        ->assertOk()
        ->assertJsonPath('data.trainer.id', $this->trainer->id);

    $this->actingAs($this->owner, 'center_user')
        ->patchJson("{$this->url}/{$member->id}", ['trainer_id' => $takenTrainer->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['trainer_id']);

    $this->actingAs($this->owner, 'center_user')
        ->patchJson("{$this->url}/{$member->id}", ['trainer_id' => $otherTrainer->id])
        ->assertOk()
        ->assertJsonPath('data.trainer.id', $otherTrainer->id);
});

test('a user granted users.manage manages users within their own permissions', function (): void {
    $manager = UserFactory::new()->create();
    assignToCenter($this->center, $manager);
    $manager->syncCenterPermissions($this->center, [CenterUserPermission::ManageUsers, CenterUserPermission::ViewRatings]);

    $this->actingAs($manager, 'center_user')->getJson($this->url)->assertOk();

    $this->actingAs($manager, 'center_user')
        ->postJson($this->url, ['name' => 'X', 'phone' => '0555555555', 'trainer_id' => $this->trainer->id, 'permissions' => ['ratings.view', 'reservations.manage']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions.1']);

    $this->actingAs($manager, 'center_user')
        ->postJson($this->url, ['name' => 'X', 'phone' => '0555555555', 'trainer_id' => $this->trainer->id, 'permissions' => ['ratings.view']])
        ->assertCreated();

    $this->actingAs($manager, 'center_user')
        ->patchJson("{$this->url}/{$manager->id}", ['permissions' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user']);

    $this->actingAs($manager, 'center_user')
        ->deleteJson("{$this->url}/{$manager->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user']);
});

test('the primary user cannot be updated or removed', function (): void {
    $this->actingAs($this->owner, 'center_user')
        ->patchJson("{$this->url}/{$this->owner->id}", ['permissions' => []])
        ->assertUnprocessable();

    $this->actingAs($this->owner, 'center_user')
        ->deleteJson("{$this->url}/{$this->owner->id}")
        ->assertUnprocessable();
});

test('users of another center are not found', function (): void {
    $stranger = UserFactory::new()->create();
    assignToCenter(CenterFactory::new()->create(), $stranger);

    $this->actingAs($this->owner, 'center_user')
        ->getJson("{$this->url}/{$stranger->id}")
        ->assertNotFound();

    $this->actingAs($this->owner, 'center_user')
        ->deleteJson("{$this->url}/{$stranger->id}")
        ->assertNotFound();
});

test('removing a user revokes access and deletes the account when it has no center left', function (): void {
    $member = UserFactory::new()->create();
    assignToCenter($this->center, $member);
    $member->syncCenterPermissions($this->center, [CenterUserPermission::ManageTrainingServices]);
    app(CenterAuthService::class)->issueCenterAccessToken($member, $this->center);

    $this->actingAs($this->owner, 'center_user')
        ->deleteJson("{$this->url}/{$member->id}")
        ->assertNoContent();

    expect($member->centers()->exists())->toBeFalse()
        ->and($member->tokens()->count())->toBe(0)
        ->and($member->fresh()->trashed())->toBeTrue()
        ->and($member->centerPermissionNames($this->center))->toBe([]);
});

test('removing a user that belongs to another center keeps the account', function (): void {
    $member = UserFactory::new()->create();
    $otherCenter = CenterFactory::new()->create();
    assignToCenter($this->center, $member);
    assignToCenter($otherCenter, $member);
    app(CenterAuthService::class)->issueCenterAccessToken($member, $this->center);
    app(CenterAuthService::class)->issueCenterAccessToken($member, $otherCenter);

    $this->actingAs($this->owner, 'center_user')
        ->deleteJson("{$this->url}/{$member->id}")
        ->assertNoContent();

    expect($member->fresh()->trashed())->toBeFalse()
        ->and($member->tokens()->count())->toBe(1)
        ->and($member->centers()->pluck('centers.id')->all())->toBe([$otherCenter->id]);
});

test('center endpoints require the matching permission for non-primary users', function (): void {
    $member = UserFactory::new()->create();
    assignToCenter($this->center, $member);
    $blockedClientsUrl = "/api/v1/centers/{$this->center->id}/blocked-clients";

    $this->actingAs($member, 'center_user')->getJson($blockedClientsUrl)->assertForbidden();

    $member->syncCenterPermissions($this->center, [CenterUserPermission::ManageBlockedClients]);

    $this->actingAs($member, 'center_user')->getJson($blockedClientsUrl)->assertOk();

    $this->center->users()->updateExistingPivot($member->id, ['status' => ActivationStatus::INACTIVE->value]);

    $this->actingAs($member, 'center_user')->getJson($blockedClientsUrl)->assertForbidden();
});
