<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Reservations\Actions\BookFromPackage;
use Modules\Reservations\Actions\CreateReservation;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationLineKind;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Events\ReservationCreated;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Enums\EventOccurrenceType;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Event as EventService;
use Modules\Services\Models\ServicePackage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->client = ClientFactory::new()->create();
    $this->service = BookingFixtures::riding($this->center, maxTicketsPerHour: 3);
    $this->option = $this->service->priceOptions()->first();
});

test('stores a pending reservation from the quote and announces it', function (): void {
    Event::fake([ReservationCreated::class]);

    $reservation = app(CreateReservation::class)->execute(
        BookingFixtures::request($this->service, $this->client, '2026-10-26', [
            'startTime' => '10:00',
            'priceOptionId' => $this->option->id,
            'quantity' => 2,
        ]),
        ReservationSource::Client,
        notes: 'First ride',
    );

    expect($reservation->status)->toBe(ReservationStatus::Pending)
        ->and($reservation->source)->toBe(ReservationSource::Client)
        ->and($reservation->center_id)->toBe($this->center->id)
        ->and($reservation->client_id)->toBe($this->client->id)
        ->and($reservation->date->toDateString())->toBe('2026-10-26')
        ->and($reservation->start_time)->toBe('10:00:00')
        ->and($reservation->end_time)->toBe('11:00:00')
        ->and((float) $reservation->total_amount)->toBe(200.0)
        ->and($reservation->notes)->toBe('First ride');

    Event::assertDispatched(ReservationCreated::class, fn (ReservationCreated $event) => $event->reservation->is($reservation));
});

test('stored reservations take places from later bookings', function (): void {
    $book = fn (int $quantity) => app(CreateReservation::class)->execute(
        BookingFixtures::request($this->service, $this->client, '2026-10-26', [
            'startTime' => '10:00',
            'priceOptionId' => $this->option->id,
            'quantity' => $quantity,
        ]),
        ReservationSource::Client,
    );

    $book(2);

    expect(fn () => $book(2))->toThrow(ReservationNotAllowedException::class, 'Not enough places left: 1 available.')
        ->and(Reservation::query()->count())->toBe(1);
});

test('stores the ticket line of an event reservation', function (): void {
    $event = EventService::query()->create([
        'occurrence_type' => EventOccurrenceType::General,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-01',
        'open_date' => '2026-10-01',
        'close_date' => '2026-10-31',
        'max_tickets_per_day' => 20,
    ]);
    $service = BookingFixtures::service($this->center, $event, ServiceType::Event);
    $adult = $service->priceOptions()->create(['name' => 'Adult', 'price' => 60]);

    $reservation = app(CreateReservation::class)->execute(
        BookingFixtures::request($service, $this->client, '2026-11-01', [
            'lines' => [['price_option_id' => $adult->id, 'quantity' => 2]],
        ]),
        ReservationSource::Center,
    );

    expect($reservation->quantity)->toBe(2)
        ->and($reservation->lines)->toHaveCount(1)
        ->and($reservation->lines->first()->kind)->toBe(ReservationLineKind::Ticket)
        ->and((float) $reservation->lines->first()->subtotal)->toBe(120.0);
});

describe('booking from a package', function (): void {
    beforeEach(function (): void {
        $servicePackage = ServicePackage::query()->create([
            'service_id' => $this->service->id,
            'price_option_id' => $this->option->id,
            'count' => 2,
            'price' => 180,
            'duration' => 1,
        ]);
        $this->package = ClientPackageFactory::new()->forServicePackage($servicePackage)->create(['client_id' => $this->client->id]);
        $this->bookFromPackage = fn (string $startTime) => app(BookFromPackage::class)->execute(
            $this->package,
            CarbonImmutable::parse('2026-10-26'),
            $startTime,
            ReservationSource::Client,
        );
    });

    test('spends a credit on a confirmed free reservation', function (): void {
        $reservation = ($this->bookFromPackage)('09:00');

        expect($reservation->status)->toBe(ReservationStatus::Confirmed)
            ->and($reservation->client_package_id)->toBe($this->package->id)
            ->and($reservation->quantity)->toBe(1)
            ->and((float) $reservation->total_amount)->toBe(0.0)
            ->and($this->package->fresh()->remaining_sessions)->toBe(1)
            ->and($this->package->fresh()->status)->toBe(ClientPackageStatus::Active);
    });

    test('the last credit exhausts the package and no more can be booked', function (): void {
        ($this->bookFromPackage)('09:00');
        ($this->bookFromPackage)('10:00');

        expect($this->package->fresh()->status)->toBe(ClientPackageStatus::Exhausted)
            ->and(fn () => ($this->bookFromPackage)('11:00'))
            ->toThrow(ReservationNotAllowedException::class, 'This package has no sessions left or has expired.');
    });

    test('a failed booking keeps the credit', function (): void {
        expect(fn () => ($this->bookFromPackage)('11:30'))->toThrow(ReservationNotAllowedException::class)
            ->and($this->package->fresh()->remaining_sessions)->toBe(2);
    });
});
