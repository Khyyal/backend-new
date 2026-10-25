<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\RecreationalRiding;
use Modules\Services\Models\ServicePackage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->client = ClientFactory::new()->create();
    $this->riding = RecreationalRiding::query()->create(['max_tickets_per_hour' => 4]);
    $this->service = BookingFixtures::service($this->center, $this->riding, ServiceType::RecreationRiding);
    $this->halfHour = $this->service->priceOptions()->create(['price' => 80, 'quantity' => 30, 'unit' => PriceOptionUnit::MINUTE]);
    $this->hour = $this->service->priceOptions()->create(['price' => 150, 'quantity' => 60, 'unit' => PriceOptionUnit::MINUTE]);
    BookingFixtures::enableSettings($this->service, ['enable_single_session_booking', 'enable_package_booking']);
    BookingFixtures::schedule($this->riding, 1, '09:00', '12:00');

    $this->book = fn (array $overrides = []) => BookingFixtures::quote($this->service, $this->client, '2026-10-26', [
        'startTime' => '10:00',
        'priceOptionId' => $this->hour->id,
        ...$overrides,
    ]);
});

test('prices a slot by its duration option and quantity', function (): void {
    $quote = ($this->book)(['quantity' => 3]);

    expect($quote->startTime)->toBe('10:00:00')
        ->and($quote->endTime)->toBe('11:00:00')
        ->and($quote->unitPrice)->toBe(150.0)
        ->and($quote->subtotal)->toBe(450.0)
        ->and($quote->priceOptionId)->toBe($this->hour->id)
        ->and($quote->trainerId)->toBeNull();
});

test('requires a start time and a duration option of the service', function (array $overrides, string $message): void {
    expect(fn () => ($this->book)($overrides))->toThrow(ReservationNotAllowedException::class, $message);
})->with([
    'no start time' => [['startTime' => null], 'A start time is required for this service.'],
    'no option' => [['priceOptionId' => null], 'A duration must be selected for this service.'],
    'foreign option' => [['priceOptionId' => 999_999], 'The selected option does not belong to this service.'],
]);

test('the slot must fit inside a schedule window', function (string $date, string $startTime): void {
    expect(fn () => BookingFixtures::quote($this->service, $this->client, $date, [
        'startTime' => $startTime,
        'priceOptionId' => $this->hour->id,
    ]))->toThrow(ReservationNotAllowedException::class, 'The selected time is outside the service working hours.');
})->with([
    'ends after closing' => ['2026-10-26', '11:30'],
    'starts before opening' => ['2026-10-26', '08:30'],
    'closed weekday' => ['2026-10-27', '10:00'],
]);

test('capacity counts overlapping active reservations only', function (): void {
    ReservationFactory::new()->forService($this->service)->at('2026-10-26', '10:30:00', '11:00:00')->state(['quantity' => 3])->create();
    ReservationFactory::new()->forService($this->service)->at('2026-10-26', '09:00:00', '10:00:00')->state(['quantity' => 4])->create();
    ReservationFactory::new()->forService($this->service)->at('2026-10-26', '10:00:00', '11:00:00')
        ->status(ReservationStatus::Cancelled)->state(['quantity' => 4])->create();

    expect(($this->book)()->quantity)->toBe(1)
        ->and(fn () => ($this->book)(['quantity' => 2]))
        ->toThrow(ReservationNotAllowedException::class, 'Not enough places left: 1 available.');
});

test('a rescheduled reservation does not count against itself', function (): void {
    $reservation = ReservationFactory::new()->forService($this->service)->at('2026-10-26', '10:00:00', '11:00:00')->state(['quantity' => 4])->create();

    expect(($this->book)(['quantity' => 4, 'ignoreReservationId' => $reservation->id])->quantity)->toBe(4);
});

describe('package credits', function (): void {
    beforeEach(function (): void {
        $servicePackage = ServicePackage::query()->create([
            'service_id' => $this->service->id,
            'price_option_id' => $this->halfHour->id,
            'count' => 8,
            'price' => 500,
            'duration' => 2,
        ]);
        $this->package = ClientPackageFactory::new()->forServicePackage($servicePackage)->create(['client_id' => $this->client->id]);
    });

    test('a package session is free and uses the package duration', function (): void {
        $quote = ($this->book)(['clientPackage' => $this->package, 'priceOptionId' => $this->hour->id]);

        expect($quote->priceOptionId)->toBe($this->halfHour->id)
            ->and($quote->endTime)->toBe('10:30:00')
            ->and($quote->subtotal)->toBe(0.0)
            ->and($quote->clientPackageId)->toBe($this->package->id);
    });

    test('a package session is for one person', function (): void {
        ($this->book)(['clientPackage' => $this->package, 'quantity' => 2]);
    })->throws(ReservationNotAllowedException::class, 'A package session is for a single person.');

    test('package booking must be enabled', function (): void {
        $this->service->settings()->where('key', 'enable_package_booking')->update(['value' => '0']);

        ($this->book)(['clientPackage' => $this->package]);
    })->throws(ReservationNotAllowedException::class, 'Package booking is not enabled for this service.');

    test('an expired, exhausted or inactive package cannot be used', function (array $state): void {
        $this->package->update($state);

        expect(fn () => ($this->book)(['clientPackage' => $this->package->fresh()]))
            ->toThrow(ReservationNotAllowedException::class, 'This package has no sessions left or has expired.');
    })->with([
        'expired' => [['expires_at' => '2026-10-19 00:00:00']],
        'exhausted' => [['remaining_sessions' => 0]],
        'pending' => [['status' => ClientPackageStatus::Pending]],
    ]);

    test('another client\'s package cannot be used', function (): void {
        BookingFixtures::quote($this->service, ClientFactory::new()->create(), '2026-10-26', [
            'startTime' => '10:00',
            'clientPackage' => $this->package,
        ]);
    })->throws(ReservationNotAllowedException::class, 'This package cannot be used for this service.');
});
