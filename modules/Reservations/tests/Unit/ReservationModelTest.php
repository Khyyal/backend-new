<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Database\Factories\ReservationLineFactory;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Models\Reservation;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('factories build a reservation with its lines and package', function (): void {
    $package = ClientPackageFactory::new()->create();
    $reservation = ReservationFactory::new()->forService($package->service)->create([
        'client_id' => $package->client_id,
        'client_package_id' => $package->id,
    ]);
    ReservationLineFactory::new()->count(2)->create(['reservation_id' => $reservation->id]);

    $reservation = Reservation::query()->with(['lines', 'clientPackage', 'service', 'client'])->findOrFail($reservation->id);

    expect($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->source)->toBe(ReservationSource::Client)
        ->and($reservation->center_id)->toBe($reservation->service->center_id)
        ->and($reservation->lines)->toHaveCount(2)
        ->and($reservation->clientPackage->is($package))->toBeTrue()
        ->and($reservation->client->reservations()->count())->toBe(1)
        ->and($package->client->packages()->count())->toBe(1)
        ->and($reservation->getMorphClass())->toBe('reservation')
        ->and($package->getMorphClass())->toBe('client_package');
});

test('a package is usable only while active, unexpired and with credits left', function (): void {
    expect(ClientPackageFactory::new()->make()->isUsable())->toBeTrue()
        ->and(ClientPackageFactory::new()->expired()->make()->isUsable())->toBeFalse()
        ->and(ClientPackageFactory::new()->make(['remaining_sessions' => 0])->isUsable())->toBeFalse();
});
