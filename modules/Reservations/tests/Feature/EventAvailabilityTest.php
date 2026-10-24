<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Services\Availability\UpcomingAvailabilityService;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Enums\EventOccurrenceType;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Event;
use Modules\Services\Models\ServiceBlock;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-19 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->event = Event::query()->create([
        'occurrence_type' => EventOccurrenceType::SpecificDay,
        'start_date' => '2026-10-25',
        'end_date' => '2026-10-27',
        'open_date' => '2026-10-15',
        'close_date' => '2026-10-26',
        'max_tickets_per_day' => 10,
    ]);
    $this->service = BookingFixtures::service($this->center, $this->event, ServiceType::Event);
    $this->availability = fn (): ?array => app(UpcomingAvailabilityService::class)
        ->forService($this->service->fresh()->load('serviceable', 'settings'));
});

test('lists every event day with its tickets', function (): void {
    ReservationFactory::new()->forService($this->service)->at('2026-10-25')->create(['quantity' => 4]);
    ReservationFactory::new()->forService($this->service)->at('2026-10-26')->create(['quantity' => 10]);
    ReservationFactory::new()->forService($this->service)->at('2026-10-27')
        ->status(ReservationStatus::Cancelled)->create(['quantity' => 10]);

    expect(($this->availability)())->toBe([
        ['day' => '2026-10-25', 'available' => true, 'available_tickets' => 6, 'max_tickets' => 10],
        ['day' => '2026-10-26', 'available' => false, 'available_tickets' => 0, 'max_tickets' => 10],
        ['day' => '2026-10-27', 'available' => true, 'available_tickets' => 10, 'max_tickets' => 10],
    ]);
});

test('general events have no days', function (): void {
    $this->event->update(['occurrence_type' => EventOccurrenceType::General]);

    expect(($this->availability)())->toBe([]);
});

test('no day is available outside the booking window', function (string $now): void {
    $this->travelTo($now);

    expect(collect(($this->availability)())->pluck('available')->unique()->all())->toBe([false]);
})->with(['before open' => '2026-10-14 12:00:00', 'after close' => '2026-10-27 08:00:00']);

test('past event days are not available', function (): void {
    $this->event->update(['start_date' => '2026-10-17', 'end_date' => '2026-10-20']);

    expect(collect(($this->availability)())->pluck('available', 'day')->all())->toBe([
        '2026-10-17' => false,
        '2026-10-18' => false,
        '2026-10-19' => true,
        '2026-10-20' => true,
    ]);
});

test('only all-day blocks close a day', function (): void {
    foreach ([['2026-10-25', BlockTimeScope::AllDay], ['2026-10-26', BlockTimeScope::SpecificTime]] as [$date, $scope]) {
        ServiceBlock::query()->create([
            'center_id' => $this->center->id,
            'service_id' => $this->service->id,
            'reason' => 'Closed',
            'start_date' => $date,
            'end_date' => $date,
            'time_scope' => $scope,
        ]);
    }

    expect(collect(($this->availability)())->pluck('available', 'day')->all())->toBe([
        '2026-10-25' => false,
        '2026-10-26' => true,
        '2026-10-27' => true,
    ]);
});
