<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Models\IdempotencyKey;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->client = ClientFactory::new()->create();
    $this->service = BookingFixtures::riding($this->center, maxTicketsPerHour: 3);
    $this->option = $this->service->priceOptions()->first();

    $this->body = fn (array $overrides = []) => [
        'service_id' => $this->service->id,
        'date' => '2026-10-26',
        'start_time' => '10:00',
        'price_option_id' => $this->option->id,
        'payment_method' => 'online',
        ...$overrides,
    ];

    $this->book = fn (string $key, array $overrides = []) => $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/reservations', ($this->body)($overrides), ['Idempotency-Key' => $key]);
});

test('client write endpoints require an idempotency key', function (string $uri): void {
    $this->actingAs($this->client, 'client')
        ->withoutIdempotencyKey()
        ->postJson($uri, [])
        ->assertBadRequest()
        ->assertJsonPath('code', 'idempotency_key_required')
        ->assertJsonPath('message', 'Send an Idempotency-Key header (up to 100 characters) with this request.');

    expect(IdempotencyKey::query()->count())->toBe(0);
})->with([
    '/api/v1/clients/reservations',
    '/api/v1/clients/reservations/1/cancel',
    '/api/v1/clients/packages',
    '/api/v1/clients/packages/1/reservations',
    '/api/v1/clients/memberships',
    '/api/v1/clients/memberships/1/visits',
]);

test('center write endpoints require an idempotency key', function (string $method, string $path): void {
    $user = UserFactory::new()->create();
    $this->center->users()->attach($user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations, CenterUserPermission::EditReservations]);

    $this->actingAs($user, 'center_user')
        ->withoutIdempotencyKey()
        ->json($method, "/api/v1/centers/{$this->center->id}/reservations{$path}")
        ->assertBadRequest()
        ->assertJsonPath('code', 'idempotency_key_required');
})->with([
    ['POST', ''],
    ['PATCH', '/1/reschedule'],
    ['POST', '/1/cancel'],
    ['POST', '/1/complete'],
    ['POST', '/1/no-show'],
]);

test('quotes do not need an idempotency key', function (): void {
    $this->actingAs($this->client, 'client')
        ->withoutIdempotencyKey()
        ->postJson('/api/v1/clients/reservations/quote', ($this->body)())
        ->assertOk();
});

test('a retried booking replays the first response without booking again', function (): void {
    $first = ($this->book)('booking-1')->assertCreated();

    $retry = ($this->book)('booking-1')
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true');

    expect($retry->json())->toBe($first->json());
    expect(Reservation::query()->count())->toBe(1);
    expect(Purchase::query()->count())->toBe(1);
    expect(Payment::query()->count())->toBe(1);
    $first->assertHeaderMissing('Idempotent-Replayed');
});

test('reusing a key for a different request is rejected', function (): void {
    ($this->book)('booking-1')->assertCreated();

    ($this->book)('booking-1', ['start_time' => '11:00'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'idempotency_key_reused');

    expect(Reservation::query()->count())->toBe(1);
});

test('a failed request releases its key for a corrected retry', function (): void {
    ($this->book)('booking-1', ['date' => '2026-10-19'])->assertUnprocessable();

    expect(IdempotencyKey::query()->count())->toBe(0);

    ($this->book)('booking-1')->assertCreated()->assertHeaderMissing('Idempotent-Replayed');

    expect(Reservation::query()->count())->toBe(1);
});

test('keys are scoped to the client', function (): void {
    ($this->book)('shared-key')->assertCreated();

    $this->actingAs(ClientFactory::new()->create(), 'client')
        ->postJson('/api/v1/clients/reservations', ($this->body)(), ['Idempotency-Key' => 'shared-key'])
        ->assertCreated()
        ->assertHeaderMissing('Idempotent-Replayed');

    expect(Reservation::query()->count())->toBe(2);
});

test('a key whose request is still running is refused', function (): void {
    ($this->book)('booking-1')->assertCreated();
    IdempotencyKey::query()->update(['status_code' => null, 'response_body' => null, 'locked_until' => now()->addMinute()]);

    ($this->book)('booking-1')
        ->assertConflict()
        ->assertHeader('Retry-After', '60')
        ->assertJsonPath('code', 'idempotency_request_in_progress');

    expect(Reservation::query()->count())->toBe(1);
});

test('a key left locked by a dead request is taken over after the lock expires', function (): void {
    ($this->book)('booking-1')->assertCreated();
    IdempotencyKey::query()->update(['status_code' => null, 'response_body' => null, 'locked_until' => now()->subSecond()]);

    ($this->book)('booking-1')->assertCreated()->assertHeaderMissing('Idempotent-Replayed');

    expect(Reservation::query()->count())->toBe(2);
    expect(IdempotencyKey::query()->sole()->status_code)->toBe(201);
});

test('a retried cancel replays the cancellation instead of failing', function (): void {
    $reservation = ReservationFactory::new()->forService($this->service)->at('2026-10-26', '10:00:00', '11:00:00')
        ->create(['client_id' => $this->client->id]);
    $cancel = fn () => $this->actingAs($this->client, 'client')
        ->postJson("/api/v1/clients/reservations/{$reservation->id}/cancel", [], ['Idempotency-Key' => 'cancel-1']);

    $cancel()->assertOk();

    $cancel()
        ->assertOk()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('data.status', 'cancelled');

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Cancelled);
});

test('stored keys are pruned after the retention window', function (): void {
    ($this->book)('booking-1')->assertCreated();
    $this->travel(25)->hours();

    $this->artisan('model:prune', ['--model' => [IdempotencyKey::class]])->assertSuccessful();

    expect(IdempotencyKey::query()->count())->toBe(0);
});
