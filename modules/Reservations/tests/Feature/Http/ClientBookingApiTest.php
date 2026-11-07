<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Reservations\Database\Factories\ClientMembershipFactory;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->client = ClientFactory::new()->create();
});

test('it requires a client token', function (): void {
    $this->getJson('/api/v1/clients/bookings')->assertUnauthorized();
});

test('it lists only active bookings of the client', function (): void {
    $reservation = ReservationFactory::new()->for($this->client)->create();
    $package = ClientPackageFactory::new()->for($this->client)->create();
    $membership = ClientMembershipFactory::new()->for($this->client)->create();

    ReservationFactory::new()->for($this->client)->status(ReservationStatus::Cancelled)->create();
    ReservationFactory::new()->for($this->client)->at('2026-10-10')->create();
    ReservationFactory::new()->for($this->client)->create(['client_package_id' => $package->id]);
    ClientPackageFactory::new()->for($this->client)->expired()->create();
    ClientPackageFactory::new()->for($this->client)->create(['status' => ClientPackageStatus::Exhausted, 'remaining_sessions' => 0]);
    ClientMembershipFactory::new()->for($this->client)->expired()->create();
    ClientMembershipFactory::new()->for($this->client)->pending()->create();
    ReservationFactory::new()->create();
    ClientPackageFactory::new()->create();
    ClientMembershipFactory::new()->create();

    $response = $this->actingAs($this->client, 'client')
        ->getJson('/api/v1/clients/bookings')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    expect(collect($response->json('data'))->map(fn (array $item): string => $item['kind'].':'.$item['data']['id'])->sort()->values()->all())
        ->toBe(collect(["reservation:{$reservation->id}", "package:{$package->id}", "membership:{$membership->id}"])->sort()->values()->all());
});

test('type filters the feed', function (): void {
    ReservationFactory::new()->for($this->client)->create();
    ClientPackageFactory::new()->for($this->client)->create();
    ClientMembershipFactory::new()->for($this->client)->create();

    $this->actingAs($this->client, 'client');

    $this->getJson('/api/v1/clients/bookings?type=all')->assertJsonCount(3, 'data');
    $this->getJson('/api/v1/clients/bookings?type=packages')->assertJsonCount(1, 'data')->assertJsonPath('data.0.kind', 'package');
    $this->getJson('/api/v1/clients/bookings?type=membership')->assertJsonCount(1, 'data')->assertJsonPath('data.0.kind', 'membership');
    $this->getJson('/api/v1/clients/bookings?type=other')->assertUnprocessable();
});

test('pagination spans all three models newest first', function (): void {
    foreach (range(1, 8) as $i) {
        $this->travelTo(now()->addMinute());
        ReservationFactory::new()->for($this->client)->create();
        ClientPackageFactory::new()->for($this->client)->create();
        $this->travelTo(now()->addMinute());
        ClientMembershipFactory::new()->for($this->client)->create();
    }

    $this->actingAs($this->client, 'client');

    $first = $this->getJson('/api/v1/clients/bookings')->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.total', 24);
    $second = $this->getJson('/api/v1/clients/bookings?page=2')->assertOk()->assertJsonCount(9, 'data');

    $all = collect([...$first->json('data'), ...$second->json('data')])->map(fn (array $item): string => $item['kind'].':'.$item['data']['id']);

    expect($all->unique())->toHaveCount(24)
        ->and($first->json('data.0.kind'))->toBe('membership');
});

test('status in_active lists the history and never overlaps the active feed', function (): void {
    $active = [
        ReservationFactory::new()->for($this->client)->create(),
        ClientPackageFactory::new()->for($this->client)->create(),
        ClientMembershipFactory::new()->for($this->client)->create(),
    ];

    $cancelled = ReservationFactory::new()->for($this->client)->status(ReservationStatus::Cancelled)->create();
    $past = ReservationFactory::new()->for($this->client)->at('2026-10-10')->create();
    $expiredPackage = ClientPackageFactory::new()->for($this->client)->expired()->create();
    $exhausted = ClientPackageFactory::new()->for($this->client)->create(['status' => ClientPackageStatus::Exhausted, 'remaining_sessions' => 0]);
    $expiredMembership = ClientMembershipFactory::new()->for($this->client)->expired()->create();

    ReservationFactory::new()->for($this->client)->create(['client_package_id' => $active[1]->id]);
    ClientMembershipFactory::new()->for($this->client)->pending()->create();
    ReservationFactory::new()->status(ReservationStatus::Cancelled)->create();

    $this->actingAs($this->client, 'client');

    $keys = fn (string $query): array => collect($this->getJson("/api/v1/clients/bookings{$query}")->assertOk()->json('data'))
        ->map(fn (array $item): string => $item['kind'].':'.$item['data']['id'])->sort()->values()->all();

    expect($keys('?status=in_active'))->toBe(collect([
        "reservation:{$cancelled->id}",
        "reservation:{$past->id}",
        "package:{$expiredPackage->id}",
        "package:{$exhausted->id}",
        "membership:{$expiredMembership->id}",
    ])->sort()->values()->all())
        ->and($keys('?status=in_active&type=packages'))->toHaveCount(2)
        ->and($keys('?status=in_active&type=membership'))->toHaveCount(1)
        ->and($keys(''))->toHaveCount(3)
        ->and($keys('?status=active'))->toBe($keys(''));

    $this->getJson('/api/v1/clients/bookings?status=other')->assertUnprocessable();
});
