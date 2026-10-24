<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Services\Availability\UpcomingAvailabilityService;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Database\Factories\TrainerFactory;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Resort;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceBlock;
use Modules\Services\Models\TrainingService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    // Monday 2026-10-19.
    $this->travelTo('2026-10-19 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->training = function (): Service {
        $service = BookingFixtures::service($this->center, TrainingService::query()->create(), ServiceType::Training);
        BookingFixtures::enableSettings($service, ['enable_single_session_booking']);

        return $service;
    };
    $this->service = ($this->training)();

    // Sara: Mondays 09:00-11:00, 3 per hour. Omar: Tuesdays 16:00-17:00, 1 per hour.
    $this->sara = TrainerFactory::new()->create(['center_id' => $this->center->id, 'name' => 'Sara', 'max_tickets_per_hour' => 3]);
    BookingFixtures::schedule($this->sara, 1, '09:00', '11:00');
    $this->omar = TrainerFactory::new()->create(['center_id' => $this->center->id, 'name' => 'Omar', 'max_tickets_per_hour' => 1]);
    BookingFixtures::schedule($this->omar, 2, '16:00', '17:00');
    $this->service->serviceable->trainers()->attach([$this->sara->id, $this->omar->id]);

    $this->availability = fn (?Service $service = null): array => collect(app(UpcomingAvailabilityService::class)
        ->forService(($service ?? $this->service)->fresh()->load('serviceable', 'settings')))
        ->map(fn (array $days) => collect($days)->keyBy('date')->all())
        ->all();
});

test('gives each trainer 15 days from their own schedules and hourly limit', function (): void {
    $availability = ($this->availability)();

    expect(array_keys($availability))->toEqualCanonicalizing([$this->sara->id, $this->omar->id])
        ->and($availability[$this->sara->id])->toHaveCount(15)
        ->and($availability[$this->sara->id]['2026-10-19']['hours'])->toBe([
            ['hour' => '09:00', 'available' => true, 'available_tickets' => 3, 'max_tickets' => 3],
            ['hour' => '10:00', 'available' => true, 'available_tickets' => 3, 'max_tickets' => 3],
        ])
        ->and($availability[$this->sara->id]['2026-10-20'])->toBe(['date' => '2026-10-20', 'available' => false, 'hours' => []])
        ->and($availability[$this->omar->id]['2026-10-19']['hours'])->toBe([])
        ->and($availability[$this->omar->id]['2026-10-20']['hours'])->toBe([
            ['hour' => '16:00', 'available' => true, 'available_tickets' => 1, 'max_tickets' => 1],
        ]);
});

test('counts a trainer\'s reservations on every service they teach', function (): void {
    $other = ($this->training)();
    $other->serviceable->trainers()->attach($this->sara->id);
    ReservationFactory::new()->forService($other)->at('2026-10-26', '09:00:00', '10:00:00')
        ->create(['trainer_id' => $this->sara->id, 'quantity' => 2]);
    ReservationFactory::new()->forService($this->service)->at('2026-10-27', '16:00:00', '17:00:00')
        ->create(['trainer_id' => $this->omar->id]);

    $availability = ($this->availability)();

    expect($availability[$this->sara->id]['2026-10-26']['hours'][0])->toMatchArray(['available' => true, 'available_tickets' => 1])
        ->and($availability[$this->sara->id]['2026-10-26']['hours'][1])->toMatchArray(['available_tickets' => 3])
        ->and($availability[$this->omar->id]['2026-10-27'])->toMatchArray(['available' => false])
        ->and($availability[$this->omar->id]['2026-10-27']['hours'][0])->toMatchArray(['available_tickets' => 0]);
});

test('a block of the service applies to every trainer', function (): void {
    ServiceBlock::query()->create([
        'center_id' => $this->center->id,
        'service_id' => $this->service->id,
        'reason' => 'Holiday',
        'start_date' => '2026-10-26',
        'end_date' => '2026-10-27',
        'time_scope' => BlockTimeScope::AllDay,
    ]);

    $availability = ($this->availability)();

    expect($availability[$this->sara->id]['2026-10-26']['available'])->toBeFalse()
        ->and($availability[$this->omar->id]['2026-10-27']['available'])->toBeFalse()
        ->and($availability[$this->omar->id]['2026-10-20']['available'])->toBeTrue();
});

test('nothing is available when the center closes training bookings', function (): void {
    $resortService = BookingFixtures::service($this->center, Resort::query()->create(), ServiceType::Resort);
    BookingFixtures::enableSettings($resortService, ['close_all_training_bookings']);

    expect(collect(($this->availability)())->flatten(1)->pluck('available')->unique()->all())->toBe([false]);
});

test('a trainer without schedules has no hours', function (): void {
    $this->omar->schedules()->delete();

    expect(collect(($this->availability)()[$this->omar->id])->pluck('hours')->flatten()->all())->toBe([]);
});

test('a training service without trainers has empty availability', function (): void {
    expect(($this->availability)(($this->training)()))->toBe([]);
});
