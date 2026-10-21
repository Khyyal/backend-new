<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ReservationLineKind;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Resort;
use Modules\Services\Models\ServiceBlock;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->client = ClientFactory::new()->create();
    $this->resort = Resort::query()->create();
    $this->service = BookingFixtures::service($this->center, $this->resort, ServiceType::Resort);

    // Thursday (4) to Saturday (6) are priced; Sunday to Wednesday are not.
    foreach ([4 => 500, 5 => 800, 6 => 800] as $day => $price) {
        $this->service->priceOptions()->create(['name' => null, 'quantity' => $day, 'price' => $price]);
    }

    $this->breakfast = $this->service->priceOptions()->create(['name' => 'Breakfast', 'quantity' => null, 'price' => 40]);

    // 2026-10-29 is a Thursday.
    $this->book = fn (array $overrides = []) => BookingFixtures::quote($this->service, $this->client, '2026-10-29', [
        'endDate' => CarbonImmutable::parse('2026-10-31'),
        ...$overrides,
    ]);
});

test('prices each day by its weekday price and adds addons once', function (): void {
    $quote = ($this->book)(['lines' => [['price_option_id' => $this->breakfast->id, 'quantity' => 3]]]);

    expect($quote->unitPrice)->toBe(2100.0)
        ->and($quote->subtotal)->toBe(2220.0)
        ->and($quote->endDate->toDateString())->toBe('2026-10-31')
        ->and($quote->lines)->toHaveCount(1)
        ->and($quote->lines[0]['kind'])->toBe(ReservationLineKind::Addon)
        ->and($quote->lines[0]['subtotal'])->toBe(120.0);
});

test('a single day defaults the end date to the start date', function (): void {
    $quote = ($this->book)(['endDate' => null]);

    expect($quote->subtotal)->toBe(500.0)->and($quote->endDate->toDateString())->toBe('2026-10-29');
});

test('every day of the range must be priced', function (): void {
    ($this->book)(['endDate' => CarbonImmutable::parse('2026-11-01')]);
})->throws(ReservationNotAllowedException::class, 'The resort is not available on 2026-11-01.');

test('day prices cannot be bought as addons', function (): void {
    $dayPrice = $this->service->priceOptions()->where('quantity', 4)->first();

    ($this->book)(['lines' => [['price_option_id' => $dayPrice->id, 'quantity' => 1]]]);
})->throws(ReservationNotAllowedException::class, 'The selected option does not belong to this service.');

test('the end date cannot precede the start date', function (): void {
    ($this->book)(['endDate' => CarbonImmutable::parse('2026-10-28')]);
})->throws(ReservationNotAllowedException::class, 'The end date must be on or after the start date.');

test('the resort is reserved as a whole', function (): void {
    ($this->book)(['quantity' => 2]);
})->throws(ReservationNotAllowedException::class, 'A resort is reserved as a whole.');

test('overlapping stays are rejected', function (): void {
    ReservationFactory::new()->forService($this->service)->state(['date' => '2026-10-31', 'end_date' => '2026-11-02'])->create();

    ($this->book)();
})->throws(ReservationNotAllowedException::class, 'The resort is already reserved for some of the selected days.');

test('cancelled and adjacent stays do not block the range', function (): void {
    ReservationFactory::new()->forService($this->service)->state(['date' => '2026-10-29', 'end_date' => '2026-10-31'])
        ->status(ReservationStatus::Cancelled)->create();
    ReservationFactory::new()->forService($this->service)->state(['date' => '2026-10-22', 'end_date' => '2026-10-24'])->create();

    expect(($this->book)()->subtotal)->toBe(2100.0);
});

test('a blocked day inside the range prevents booking', function (): void {
    ServiceBlock::query()->create([
        'reason' => 'Maintenance',
        'center_id' => $this->center->id,
        'service_id' => $this->service->id,
        'start_date' => '2026-10-30',
        'end_date' => '2026-10-30',
        'time_scope' => BlockTimeScope::AllDay,
    ]);

    ($this->book)();
})->throws(ReservationNotAllowedException::class, 'The service is closed at the selected time.');
