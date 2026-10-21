<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Enums\VisitEnterType;
use Modules\Services\Models\Visit;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->client = ClientFactory::new()->create();

    $this->makeVisit = function (VisitEnterType $enterType): array {
        $visit = Visit::query()->create(['enter_type' => $enterType, 'max_tickets_per_day' => 10]);
        $service = BookingFixtures::service($this->center, $visit, ServiceType::Visit);
        $service->priceOptions()->create(['name' => null, 'price' => 25]);

        return [$visit, $service];
    };
});

describe('all day', function (): void {
    beforeEach(function (): void {
        [$this->visit, $this->service] = ($this->makeVisit)(VisitEnterType::AllDay);
        BookingFixtures::schedule($this->visit, 1, null, null);
    });

    test('books an open day without a time', function (): void {
        $quote = BookingFixtures::quote($this->service, $this->client, '2026-10-26', ['quantity' => 4]);

        expect($quote->startTime)->toBeNull()
            ->and($quote->endTime)->toBeNull()
            ->and($quote->subtotal)->toBe(100.0);
    });

    test('a day without a schedule is closed', function (): void {
        BookingFixtures::quote($this->service, $this->client, '2026-10-27');
    })->throws(ReservationNotAllowedException::class, 'The selected time is outside the service working hours.');

    test('the daily ticket limit applies', function (): void {
        ReservationFactory::new()->forService($this->service)->at('2026-10-26')->state(['quantity' => 8])->create();

        BookingFixtures::quote($this->service, $this->client, '2026-10-26', ['quantity' => 3]);
    })->throws(ReservationNotAllowedException::class, 'Not enough places left: 2 available.');

    test('packages cannot be used', function (): void {
        BookingFixtures::quote($this->service, $this->client, '2026-10-26', ['clientPackage' => ClientPackageFactory::new()->create()]);
    })->throws(ReservationNotAllowedException::class, 'Packages cannot be used for this service.');
});

describe('specific time', function (): void {
    beforeEach(function (): void {
        [$this->visit, $this->service] = ($this->makeVisit)(VisitEnterType::SpecificTime);
        BookingFixtures::schedule($this->visit, 1, '09:00', '11:00');
        BookingFixtures::schedule($this->visit, 1, '15:00', '17:00');
    });

    test('books one of the day windows', function (): void {
        $quote = BookingFixtures::quote($this->service, $this->client, '2026-10-26', ['startTime' => '15:00']);

        expect($quote->startTime)->toBe('15:00:00')->and($quote->endTime)->toBe('17:00:00');
    });

    test('the start time must open a window', function (?string $startTime, string $message): void {
        expect(fn () => BookingFixtures::quote($this->service, $this->client, '2026-10-26', ['startTime' => $startTime]))
            ->toThrow(ReservationNotAllowedException::class, $message);
    })->with([
        'missing' => [null, 'A start time is required for this service.'],
        'inside a window' => ['10:00', 'The selected time is outside the service working hours.'],
    ]);

    test('the daily ticket limit spans every window', function (): void {
        ReservationFactory::new()->forService($this->service)->at('2026-10-26', '09:00:00', '11:00:00')->state(['quantity' => 10])->create();

        BookingFixtures::quote($this->service, $this->client, '2026-10-26', ['startTime' => '15:00']);
    })->throws(ReservationNotAllowedException::class, 'Not enough places left: 0 available.');
});
