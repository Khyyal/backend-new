<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Models\SystemUser;
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
    $this->client = ClientFactory::new()->create();
    $this->service = BookingFixtures::riding($this->center);
    $this->url = "/api/v1/centers/{$this->center->id}/reservations";

    $this->reservation = fn (array $attributes = []) => ReservationFactory::new()->forService($this->service)
        ->at('2026-10-26', '10:00:00', '11:00:00')
        ->create([
            'client_id' => $this->client->id,
            'price_option_id' => $this->service->priceOptions()->first()->id,
            'unit_price' => 100,
            'subtotal' => 100,
            'total_amount' => 100,
            ...$attributes,
        ]);
});

test('guests and users of other centers are rejected', function (): void {
    $this->getJson($this->url)->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson($this->url)
        ->assertForbidden();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->postJson($this->url, [])
        ->assertForbidden();
});

test('lists the center reservations with filters', function (): void {
    $kept = ($this->reservation)();
    ($this->reservation)(['status' => ReservationStatus::Cancelled]);
    ($this->reservation)(['date' => '2026-11-02']);
    ReservationFactory::new()->create();

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?filter[status]=confirmed&filter[date]=2026-10-26")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $kept->id)
        ->assertJsonPath('data.0.client.id', $this->client->id);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?filter[from]=2026-10-27&sort=-date")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.date', '2026-11-02');
});

test('a reservation of another center is not found', function (): void {
    $other = ReservationFactory::new()->create();

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/{$other->id}")
        ->assertNotFound();
});

test('books a walk-in paid at the desk', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, [
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'date' => '2026-10-26',
            'start_time' => '09:00',
            'price_option_id' => $this->service->priceOptions()->first()->id,
            'payment_method' => 'manual',
            'notes' => 'Regular rider',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.source', 'center')
        ->assertJsonPath('data.notes', 'Regular rider')
        ->assertJsonPath('checkout.payment.status', 'succeeded')
        ->assertJsonPath('checkout.purchase.status', 'confirmed');
});

test('a walk-in must be for a service of this center', function (): void {
    $otherService = BookingFixtures::riding(CenterFactory::new()->create());

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, [
            'client_id' => $this->client->id,
            'service_id' => $otherService->id,
            'date' => '2026-10-26',
            'start_time' => '09:00',
            'payment_method' => 'manual',
        ])
        ->assertNotFound();
});

test('reschedules a reservation', function (): void {
    $reservation = ($this->reservation)();

    $this->actingAs($this->user, 'center_user')
        ->patchJson("{$this->url}/{$reservation->id}/reschedule", ['date' => '2026-11-02', 'start_time' => '11:00'])
        ->assertOk()
        ->assertJsonPath('data.date', '2026-11-02')
        ->assertJsonPath('data.start_time', '11:00')
        ->assertJsonPath('data.reschedules.0.date', '2026-10-26');
});

test('cancels inside the client cutoff and records the staff member', function (): void {
    $this->travelTo('2026-10-26 09:00:00');
    $reservation = ($this->reservation)();

    $this->actingAs($this->user, 'center_user')
        ->postJson("{$this->url}/{$reservation->id}/cancel", ['reason' => 'Weather'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($reservation->fresh()->cancelledBy->is($this->user))->toBeTrue();
});

test('records attendance after the start', function (string $action, string $status): void {
    $this->travelTo('2026-10-26 10:15:00');
    $reservation = ($this->reservation)();

    $this->actingAs($this->user, 'center_user')
        ->postJson("{$this->url}/{$reservation->id}/{$action}")
        ->assertOk()
        ->assertJsonPath('data.status', $status);
})->with([['complete', 'completed'], ['no-show', 'no_show']]);

test('attendance before the start is rejected', function (): void {
    $reservation = ($this->reservation)();

    $this->actingAs($this->user, 'center_user')
        ->postJson("{$this->url}/{$reservation->id}/complete")
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'not_started_yet');
});

test('a system user manages reservations through the admin routes', function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $admin = SystemUser::factory()->superAdmin()->create();
    $reservation = ($this->reservation)();

    $this->actingAs($admin, 'system_user')
        ->getJson("/api/v1/admin/centers/{$this->center->id}/reservations")
        ->assertOk()
        ->assertJsonPath('data.0.id', $reservation->id);

    $this->actingAs($admin, 'system_user')
        ->postJson("/api/v1/admin/centers/{$this->center->id}/reservations/{$reservation->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

test('a system user without the permission is rejected', function (): void {
    $admin = SystemUser::factory()->create();

    $this->actingAs($admin, 'system_user')
        ->getJson("/api/v1/admin/centers/{$this->center->id}/reservations")
        ->assertForbidden();
});
