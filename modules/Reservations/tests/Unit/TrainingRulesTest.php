<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Trainer;
use Modules\Services\Models\TrainingService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->client = ClientFactory::new()->create();
    $this->training = TrainingService::query()->create();
    $this->service = BookingFixtures::service($this->center, $this->training, ServiceType::Training);
    $this->option = $this->service->priceOptions()->create(['price' => 200, 'quantity' => 45, 'unit' => PriceOptionUnit::MINUTE]);
    BookingFixtures::enableSettings($this->service, ['enable_single_session_booking']);

    $this->trainer = Trainer::query()->create(['center_id' => $this->center->id, 'name' => 'Sara', 'max_tickets_per_hour' => 2]);
    $this->trainer->trainingServices()->attach($this->training);
    BookingFixtures::schedule($this->trainer, 1, '16:00', '20:00');

    $this->book = fn (array $overrides = []) => BookingFixtures::quote($this->service, $this->client, '2026-10-26', [
        'startTime' => '17:00',
        'priceOptionId' => $this->option->id,
        'trainerId' => $this->trainer->id,
        ...$overrides,
    ]);
});

test('books a slot on the trainer schedule', function (): void {
    $quote = ($this->book)(['quantity' => 2]);

    expect($quote->trainerId)->toBe($this->trainer->id)
        ->and($quote->endTime)->toBe('17:45:00')
        ->and($quote->subtotal)->toBe(400.0);
});

test('a trainer is required', function (): void {
    ($this->book)(['trainerId' => null]);
})->throws(ReservationNotAllowedException::class, 'A trainer must be selected for this service.');

test('the trainer must teach this service', function (): void {
    $other = Trainer::query()->create(['center_id' => $this->center->id, 'name' => 'Omar', 'max_tickets_per_hour' => 2]);
    BookingFixtures::schedule($other, 1, '16:00', '20:00');

    ($this->book)(['trainerId' => $other->id]);
})->throws(ReservationNotAllowedException::class, 'The selected trainer does not offer this service.');

test('the slot must fit the trainer schedule', function (): void {
    ($this->book)(['startTime' => '10:00']);
})->throws(ReservationNotAllowedException::class, 'The selected time is outside the service working hours.');

test('trainer capacity is shared across the trainer\'s services', function (): void {
    $otherTraining = TrainingService::query()->create();
    $this->trainer->trainingServices()->attach($otherTraining);
    $otherService = BookingFixtures::service($this->center, $otherTraining, ServiceType::Training);

    ReservationFactory::new()->forService($otherService)->at('2026-10-26', '17:30:00', '18:15:00')
        ->state(['trainer_id' => $this->trainer->id, 'quantity' => 2])->create();

    ($this->book)();
})->throws(ReservationNotAllowedException::class, 'Not enough places left: 0 available.');
