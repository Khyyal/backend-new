<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\RecreationalRiding;
use Modules\Services\Models\Resort;
use Modules\Services\Models\ServiceBlock;
use Modules\Support\Database\Factories\BlockFactory;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->client = ClientFactory::new()->create();
    $this->riding = RecreationalRiding::query()->create(['max_tickets_per_hour' => 5]);
    $this->service = BookingFixtures::service($this->center, $this->riding, ServiceType::RecreationRiding);
    $this->option = $this->service->priceOptions()->create(['price' => 100, 'quantity' => 60, 'unit' => PriceOptionUnit::MINUTE]);
    BookingFixtures::enableSettings($this->service, ['enable_single_session_booking']);
    BookingFixtures::schedule($this->riding, 1, '09:00', '12:00');

    $this->book = fn (array $overrides = []) => BookingFixtures::quote($this->service, $this->client, '2026-10-26', [
        'startTime' => '10:00',
        'priceOptionId' => $this->option->id,
        ...$overrides,
    ]);
});

test('an inactive service cannot be booked', function (): void {
    $this->service->update(['status' => ActivationStatus::INACTIVE]);

    ($this->book)();
})->throws(ReservationNotAllowedException::class, 'This service is not available for booking.');

test('a client blocked by the center cannot book', function (): void {
    BlockFactory::new()->byBlocker($this->center)->forBlockable($this->client)->create();

    ($this->book)();
})->throws(ReservationNotAllowedException::class, 'You cannot book at this center.');

test('single session booking must be enabled', function (): void {
    $this->service->settings()->update(['value' => '0']);

    ($this->book)();
})->throws(ReservationNotAllowedException::class, 'Single session booking is not enabled for this service.');

test('a resort setting can close all riding bookings of the center', function (): void {
    $resortService = BookingFixtures::service($this->center, Resort::query()->create(), ServiceType::Resort);
    BookingFixtures::enableSettings($resortService, ['close_all_recreational_ride_bookings']);

    ($this->book)();
})->throws(ReservationNotAllowedException::class, 'Bookings for this service are currently closed.');

test('a resort closing setting of another center has no effect', function (): void {
    $resortService = BookingFixtures::service(CenterFactory::new()->create(), Resort::query()->create(), ServiceType::Resort);
    BookingFixtures::enableSettings($resortService, ['close_all_recreational_ride_bookings']);

    expect(($this->book)()->subtotal)->toBe(100.0);
});

test('an all-day block on the date prevents booking', function (): void {
    ServiceBlock::query()->create([
        'reason' => 'Maintenance',
        'center_id' => $this->center->id,
        'service_id' => null,
        'start_date' => '2026-10-25',
        'end_date' => '2026-10-27',
        'time_scope' => BlockTimeScope::AllDay,
    ]);

    ($this->book)();
})->throws(ReservationNotAllowedException::class, 'The service is closed at the selected time.');

test('a time-scoped block only prevents overlapping slots', function (): void {
    $block = ServiceBlock::query()->create([
        'reason' => 'Maintenance',
        'center_id' => $this->center->id,
        'service_id' => $this->service->id,
        'start_date' => '2026-10-26',
        'end_date' => '2026-10-26',
        'time_scope' => BlockTimeScope::SpecificTime,
    ]);
    $block->periods()->create(['start_time' => '10:30', 'end_time' => '11:00']);

    expect(fn () => ($this->book)())->toThrow(ReservationNotAllowedException::class, 'The service is closed at the selected time.')
        ->and(($this->book)(['startTime' => '09:00'])->startTime)->toBe('09:00:00');
});

test('a past date cannot be booked', function (): void {
    BookingFixtures::quote($this->service, $this->client, '2026-10-19', [
        'startTime' => '10:00',
        'priceOptionId' => $this->option->id,
    ]);
})->throws(ReservationNotAllowedException::class, 'The selected date has already passed.');

test('horse care services cannot be reserved', function (): void {
    $this->service->update(['type' => ServiceType::HorseCare]);

    ($this->book)();
})->throws(ReservationNotAllowedException::class, 'This service type cannot be reserved.');
