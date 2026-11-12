<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Centers\Services\CenterAuthService;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Settlements\Database\Factories\CenterPayoutFactory;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function attachUser(Center $center, User $user, bool $isPrimary = false): void
{
    $center->users()->attach($user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => $isPrimary,
    ]);
}

beforeEach(function (): void {
    $this->center = CenterFactory::new()->create();
    $this->owner = UserFactory::new()->create(['password' => 'old-secret-123']);
    attachUser($this->center, $this->owner, isPrimary: true);
    $this->member = UserFactory::new()->create();
    attachUser($this->center, $this->member);
    $this->url = "/api/v1/centers/{$this->center->id}";
});

test('only the primary user can update the profile', function (): void {
    $this->patchJson("{$this->url}/profile", ['name' => 'X'])->assertUnauthorized();

    $this->actingAs($this->member, 'center_user')
        ->patchJson("{$this->url}/profile", ['name' => 'X'])->assertForbidden();

    $this->actingAs($this->owner, 'center_user')
        ->patchJson("{$this->url}/profile", ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name');

    expect($this->owner->fresh()->name)->toBe('New Name');
});

test('changing an existing password requires the current one', function (): void {
    $payload = ['password' => 'brand-new-123', 'password_confirmation' => 'brand-new-123'];

    $this->actingAs($this->owner, 'center_user')
        ->patchJson("{$this->url}/profile", $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('current_password');

    $this->actingAs($this->owner, 'center_user')
        ->patchJson("{$this->url}/profile", $payload + ['current_password' => 'wrong'])
        ->assertUnprocessable()->assertJsonValidationErrors('current_password');

    $this->actingAs($this->owner, 'center_user')
        ->patchJson("{$this->url}/profile", $payload + ['current_password' => 'old-secret-123'])
        ->assertOk();

    expect(Hash::check('brand-new-123', $this->owner->fresh()->password))->toBeTrue();
});

test('a password can be set without the current one when none exists', function (): void {
    $owner = UserFactory::new()->create(['password' => null]);
    $center = CenterFactory::new()->create();
    attachUser($center, $owner, isPrimary: true);

    $this->actingAs($owner, 'center_user')
        ->patchJson("/api/v1/centers/{$center->id}/profile", ['password' => 'first-secret-1', 'password_confirmation' => 'first-secret-1'])
        ->assertOk();

    expect(Hash::check('first-secret-1', $owner->fresh()->password))->toBeTrue();
});

test('only the primary user can delete the account', function (): void {
    $this->deleteJson($this->url)->assertUnauthorized();

    $this->actingAs($this->member, 'center_user')->deleteJson($this->url)->assertForbidden();

    expect(Center::find($this->center->id))->not->toBeNull();
});

test('deleting the account removes the center and cuts its users off while keeping their assignments', function (): void {
    $other = CenterFactory::new()->create();
    attachUser($other, $this->member);
    $token = app(CenterAuthService::class)->issueCenterAccessToken($this->owner, $this->center);

    $this->withToken($token)->deleteJson($this->url)->assertNoContent();

    expect(Center::find($this->center->id))->toBeNull()
        ->and(Center::withTrashed()->find($this->center->id))->not->toBeNull()
        ->and(User::find($this->owner->id))->toBeNull()
        ->and(User::find($this->member->id))->not->toBeNull()
        ->and($this->member->centers()->pluck('centers.id')->all())->toBe([$other->id])
        ->and($this->owner->tokens()->count())->toBe(0)
        ->and($this->center->users()->withTrashed()->count())->toBe(2);
});

test('deleting is refused while upcoming reservations are open', function (): void {
    $service = ServiceFactory::new()->create(['center_id' => $this->center->id]);
    ReservationFactory::new()->forService($service)->status(ReservationStatus::Confirmed)->create();

    $this->actingAs($this->owner, 'center_user')->deleteJson($this->url)
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'has_open_reservations');

    expect(Center::find($this->center->id))->not->toBeNull();
});

test('deleting is allowed when reservations are only cancelled or past', function (): void {
    $service = ServiceFactory::new()->create(['center_id' => $this->center->id]);
    ReservationFactory::new()->forService($service)->status(ReservationStatus::Cancelled)->create();
    ReservationFactory::new()->forService($service)->status(ReservationStatus::Confirmed)->at(now()->subWeek()->toDateString())->create();

    $this->actingAs($this->owner, 'center_user')->deleteJson($this->url)->assertNoContent();
});

test('deleting is refused with a pending payout', function (): void {
    CenterPayoutFactory::new()->create(['center_id' => $this->center->id]);

    $this->actingAs($this->owner, 'center_user')->deleteJson($this->url)
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'has_unsettled_balance');
});
