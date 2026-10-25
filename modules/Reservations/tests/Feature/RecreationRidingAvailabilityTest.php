<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Services\Availability\UpcomingAvailabilityService;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Resort;
use Modules\Services\Models\ServiceBlock;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    // Monday; the riding fixture is open on Mondays 09:00-12:00 with 4 tickets per hour.
    $this->travelTo('2026-10-19 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->service = BookingFixtures::riding($this->center);
    $this->availability = fn (): array => collect(app(UpcomingAvailabilityService::class)
        ->forService($this->service->fresh()->load('serviceable.schedules', 'settings')))
        ->keyBy('date')
        ->all();
    $this->block = fn (string $date, BlockTimeScope $scope): ServiceBlock => ServiceBlock::query()->create([
        'center_id' => $this->center->id,
        'service_id' => $this->service->id,
        'reason' => 'Maintenance',
        'start_date' => $date,
        'end_date' => $date,
        'time_scope' => $scope,
    ]);
});

test('covers today and the next 14 days with one-hour slots of the schedule windows', function (): void {
    $days = ($this->availability)();

    expect(array_keys($days))->toHaveCount(15)
        ->and(array_key_first($days))->toBe('2026-10-19')
        ->and(array_key_last($days))->toBe('2026-11-02')
        ->and($days['2026-10-19'])->toBe([
            'date' => '2026-10-19',
            'available' => true,
            'hours' => [
                ['hour' => '09:00', 'available' => true, 'available_tickets' => 4, 'max_tickets' => 4],
                ['hour' => '10:00', 'available' => true, 'available_tickets' => 4, 'max_tickets' => 4],
                ['hour' => '11:00', 'available' => true, 'available_tickets' => 4, 'max_tickets' => 4],
            ],
        ])
        ->and($days['2026-10-20'])->toBe(['date' => '2026-10-20', 'available' => false, 'hours' => []]);
});

test('skips partial hours at the end of a window', function (): void {
    BookingFixtures::schedule($this->service->serviceable, 2, '14:00', '16:30');

    expect(collect(($this->availability)()['2026-10-20']['hours'])->pluck('hour')->all())->toBe(['14:00', '15:00']);
});

test('subtracts reserved tickets of overlapping reservations', function (): void {
    ReservationFactory::new()->forService($this->service)->at('2026-10-26', '10:00:00', '11:00:00')->create(['quantity' => 3]);
    ReservationFactory::new()->forService($this->service)->at('2026-10-26', '11:00:00', '12:00:00')->create(['quantity' => 4]);
    ReservationFactory::new()->forService($this->service)->at('2026-10-26', '09:00:00', '10:00:00')
        ->status(ReservationStatus::Cancelled)->create(['quantity' => 4]);

    $hours = collect(($this->availability)()['2026-10-26']['hours'])->keyBy('hour');

    expect($hours['09:00'])->toMatchArray(['available' => true, 'available_tickets' => 4])
        ->and($hours['10:00'])->toMatchArray(['available' => true, 'available_tickets' => 1])
        ->and($hours['11:00'])->toMatchArray(['available' => false, 'available_tickets' => 0]);
});

test('ignores reservations outside the range', function (): void {
    ReservationFactory::new()->forService($this->service)->at('2026-10-12', '09:00:00', '10:00:00')->create(['quantity' => 4]);
    ReservationFactory::new()->forService($this->service)->at('2026-11-09', '09:00:00', '10:00:00')->create(['quantity' => 4]);

    expect(collect(($this->availability)())->flatMap(fn (array $day) => $day['hours'])->pluck('available_tickets')->unique()->all())
        ->toBe([4]);
});

test('hours that already started are not available', function (): void {
    $this->travelTo('2026-10-19 10:15:00');

    $day = ($this->availability)()['2026-10-19'];

    expect(collect($day['hours'])->pluck('available', 'hour')->all())
        ->toBe(['09:00' => false, '10:00' => false, '11:00' => true])
        ->and($day['available'])->toBeTrue();
});

test('an all-day block closes the day', function (): void {
    ($this->block)('2026-10-26', BlockTimeScope::AllDay);

    $day = ($this->availability)()['2026-10-26'];

    expect($day['available'])->toBeFalse()
        ->and(collect($day['hours'])->pluck('available')->unique()->all())->toBe([false]);
});

test('a timed block closes only the overlapping hours', function (): void {
    ($this->block)('2026-10-26', BlockTimeScope::SpecificTime)
        ->periods()->create(['start_time' => '10:30', 'end_time' => '11:00']);

    expect(collect(($this->availability)()['2026-10-26']['hours'])->pluck('available', 'hour')->all())
        ->toBe(['09:00' => true, '10:00' => false, '11:00' => true]);
});

test('nothing is available when the center closes riding bookings', function (): void {
    $resortService = BookingFixtures::service($this->center, Resort::query()->create(), ServiceType::Resort);
    BookingFixtures::enableSettings($resortService, ['close_all_recreational_ride_bookings']);

    expect(collect(($this->availability)())->pluck('available')->unique()->all())->toBe([false]);
});

test('nothing is available when every booking mode is disabled', function (): void {
    $this->service->settings()->whereIn('key', ['enable_single_session_booking', 'enable_package_booking'])->update(['value' => '0']);

    expect(collect(($this->availability)())->pluck('available')->unique()->all())->toBe([false]);
});

test('a riding without an hourly limit has unlimited tickets', function (): void {
    $this->service->serviceable->update(['max_tickets_per_hour' => null]);
    ReservationFactory::new()->forService($this->service)->at('2026-10-26', '09:00:00', '10:00:00')->create(['quantity' => 50]);

    expect(($this->availability)()['2026-10-26']['hours'][0])
        ->toBe(['hour' => '09:00', 'available' => true, 'available_tickets' => null, 'max_tickets' => null]);
});

test('other service types have no availability yet', function (): void {
    $resortService = BookingFixtures::service($this->center, Resort::query()->create(), ServiceType::Resort);

    expect(app(UpcomingAvailabilityService::class)->forService($resortService))->toBeNull();
});
