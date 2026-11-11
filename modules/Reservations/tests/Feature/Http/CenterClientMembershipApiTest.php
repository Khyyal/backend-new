<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Reservations\Database\Factories\ClientMembershipFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ClientMembershipLogType;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Models\ClientMembershipLog;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Membership;
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
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations]);
    $this->visit = BookingFixtures::riding($this->center);
    $this->url = "/api/v1/centers/{$this->center->id}/client-memberships";

    $this->membershipService = ServiceFactory::new()
        ->forServiceable(Membership::factory()->create(), ServiceType::Membership)
        ->create(['center_id' => $this->center->id]);

    $this->membership = fn (array $attributes = [], array $client = []) => ClientMembershipFactory::new()
        ->create([
            'client_id' => ClientFactory::new()->create($client)->id,
            'service_id' => $this->membershipService->id,
            ...$attributes,
        ]);
});

test('guests and users of other centers are rejected', function (): void {
    $this->getJson($this->url)->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson($this->url)
        ->assertForbidden();
});

test('lists only the center memberships, never pending ones', function (): void {
    $kept = ($this->membership)();
    ($this->membership)(['status' => ClientMembershipStatus::Pending, 'expires_at' => null]);
    ClientMembershipFactory::new()->create();

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $kept->id)
        ->assertJsonPath('data.0.client.id', $kept->client_id);
});

test('filters by active and not_active', function (): void {
    $active = ($this->membership)();
    $expired = ($this->membership)(['status' => ClientMembershipStatus::Expired]);
    $cancelled = ($this->membership)(['status' => ClientMembershipStatus::Cancelled]);

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
        ->assertJsonFragment(['id' => $cancelled->id]);
});

test('searches by client name or phone number', function (): void {
    $sara = ($this->membership)([], ['first_name' => 'Sara', 'last_name' => 'Belhadj', 'phone_number' => '0550111222']);
    ($this->membership)([], ['first_name' => 'Omar', 'last_name' => 'Kaci', 'phone_number' => '0661333444']);

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
    ($this->membership)(['created_at' => '2026-09-01 10:00:00']);
    $recent = ($this->membership)(['created_at' => '2026-10-15 10:00:00']);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?from=2026-10-01&to=2026-10-20")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $recent->id);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?service_id={$this->membershipService->id}")
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

test('counts active and not active memberships, ignoring pending ones', function (): void {
    ($this->membership)();
    ($this->membership)();
    ($this->membership)(['status' => ClientMembershipStatus::Expired]);
    ($this->membership)(['status' => ClientMembershipStatus::Cancelled]);
    ($this->membership)(['status' => ClientMembershipStatus::Pending, 'expires_at' => null]);
    ClientMembershipFactory::new()->create();

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/counts")
        ->assertOk()
        ->assertExactJson(['data' => ['active' => 2, 'not_active' => 2]]);

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson("{$this->url}/counts")
        ->assertForbidden();
});

test('shows a membership with its client, visits left today and booked visits', function (): void {
    $membership = ($this->membership)(['visit_service_id' => $this->visit->id, 'max_visits_per_day' => 2]);
    ReservationFactory::new()->forService($this->visit)
        ->at('2026-10-20', '10:00:00', '11:00:00')
        ->create(['client_id' => $membership->client_id, 'client_membership_id' => $membership->id]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/{$membership->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $membership->id)
        ->assertJsonPath('data.client.id', $membership->client_id)
        ->assertJsonPath('data.visits_left_today', 1)
        ->assertJsonCount(1, 'data.reservations')
        ->assertJsonPath('data.reservations.0.date', '2026-10-20')
        ->assertJsonPath('data.reservations.0.start_time', '10:00');
});

test('a pending, foreign or forbidden membership cannot be shown', function (): void {
    $pending = ($this->membership)(['status' => ClientMembershipStatus::Pending, 'expires_at' => null]);
    $foreign = ClientMembershipFactory::new()->create();
    $own = ($this->membership)();

    $this->actingAs($this->user, 'center_user')->getJson("{$this->url}/{$pending->id}")->assertNotFound();
    $this->actingAs($this->user, 'center_user')->getJson("{$this->url}/{$foreign->id}")->assertNotFound();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson("{$this->url}/{$own->id}")
        ->assertForbidden();
});

test('extends the expiry of a membership and revives an expired one', function (): void {
    $membership = ($this->membership)(['status' => ClientMembershipStatus::Expired, 'expires_at' => now()->subDay(), 'expiry_reminder_sent_at' => now()->subDays(3)]);
    $url = "{$this->url}/{$membership->id}";

    $this->actingAs($this->user, 'center_user')
        ->patchJson($url, ['expires_at' => now()->addDays(30)->toIso8601String()])
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.is_usable', true);

    expect($membership->fresh()->expiry_reminder_sent_at)->toBeNull();

    $this->actingAs($this->user, 'center_user')
        ->patchJson($url, ['expires_at' => now()->addDays(10)->toIso8601String()])
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'invalid_expiry');

    $this->actingAs($this->user, 'center_user')
        ->patchJson($url, ['expires_at' => now()->subDay()->toIso8601String()])
        ->assertJsonValidationErrors('expires_at');
});

test('extending needs the permission and a paid, uncancelled membership of this center', function (): void {
    $membership = ($this->membership)();
    $pending = ($this->membership)(['status' => ClientMembershipStatus::Pending, 'expires_at' => null]);
    $cancelled = ($this->membership)(['status' => ClientMembershipStatus::Cancelled]);
    $foreign = ClientMembershipFactory::new()->create();
    $body = ['expires_at' => now()->addYear()->toIso8601String()];

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->patchJson("{$this->url}/{$membership->id}", $body)->assertForbidden();

    foreach ([$pending, $foreign] as $notFound) {
        $this->actingAs($this->user, 'center_user')
            ->patchJson("{$this->url}/{$notFound->id}", $body)->assertNotFound();
    }

    $this->actingAs($this->user, 'center_user')
        ->patchJson("{$this->url}/{$cancelled->id}", $body)
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'membership_not_adjustable');
});

test('every check-in adds a log entry that the center can list', function (): void {
    $membership = ($this->membership)();
    $url = "{$this->url}/{$membership->id}";

    $this->actingAs($this->user, 'center_user')
        ->postJson("{$url}/check-ins", ['notes' => 'Came with two friends.'])
        ->assertCreated()
        ->assertJsonPath('data.type', 'check_in')
        ->assertJsonPath('data.notes', 'Came with two friends.')
        ->assertJsonPath('data.actor.type', 'center_user')
        ->assertJsonPath('data.actor.id', $this->user->id);

    $this->actingAs($this->user, 'center_user')->postJson("{$url}/check-ins")->assertCreated();

    $guest = ClientFactory::new()->create();
    $membership->logs()->create(['type' => ClientMembershipLogType::Invite, 'guest_client_id' => $guest->id]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$url}/logs")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.type', 'invite')
        ->assertJsonPath('data.0.guest.id', $guest->id);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$url}/logs?type=check_in")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(ClientMembershipLog::query()->where('type', ClientMembershipLogType::CheckIn)->count())->toBe(2);
});

test('check-in and logs need the permission and a usable paid membership of this center', function (): void {
    $membership = ($this->membership)();
    $expired = ($this->membership)(['status' => ClientMembershipStatus::Expired]);
    $pending = ($this->membership)(['status' => ClientMembershipStatus::Pending, 'expires_at' => null]);
    $foreign = ClientMembershipFactory::new()->create();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->postJson("{$this->url}/{$membership->id}/check-ins")->assertForbidden();
    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson("{$this->url}/{$membership->id}/logs")->assertForbidden();

    foreach ([$pending, $foreign] as $notFound) {
        $this->actingAs($this->user, 'center_user')->postJson("{$this->url}/{$notFound->id}/check-ins")->assertNotFound();
        $this->actingAs($this->user, 'center_user')->getJson("{$this->url}/{$notFound->id}/logs")->assertNotFound();
    }

    $this->actingAs($this->user, 'center_user')
        ->postJson("{$this->url}/{$expired->id}/check-ins")
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'membership_not_usable');

    expect(ClientMembershipLog::query()->count())->toBe(0);
});
