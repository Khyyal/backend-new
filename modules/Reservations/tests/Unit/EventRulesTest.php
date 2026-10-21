<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ReservationLineKind;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Enums\EventOccurrenceType;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Event;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->client = ClientFactory::new()->create();
    $this->event = Event::query()->create([
        'occurrence_type' => EventOccurrenceType::General,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-03',
        'open_date' => '2026-10-15',
        'close_date' => '2026-10-31',
        'max_tickets_per_day' => 50,
    ]);
    $this->service = BookingFixtures::service($this->center, $this->event, ServiceType::Event);
    $this->adult = $this->service->priceOptions()->create(['name' => 'Adult', 'price' => 60]);
    $this->child = $this->service->priceOptions()->create(['name' => 'Child', 'price' => 30]);

    $this->book = fn (array $lines, string $date = '2026-11-02') => BookingFixtures::quote($this->service, $this->client, $date, ['lines' => $lines]);
});

test('books tickets of one price option', function (): void {
    $quote = ($this->book)([['price_option_id' => $this->child->id, 'quantity' => 3]]);

    expect($quote->quantity)->toBe(3)
        ->and($quote->subtotal)->toBe(90.0)
        ->and($quote->priceOptionId)->toBe($this->child->id)
        ->and($quote->lines)->toHaveCount(1)
        ->and($quote->lines[0]['kind'])->toBe(ReservationLineKind::Ticket);
});

test('exactly one ticket option must be selected', function (array $lines): void {
    expect(fn () => ($this->book)($lines))->toThrow(ReservationNotAllowedException::class, 'Select exactly one ticket option.');
})->with([
    'none' => [[]],
    'two' => [fn () => [
        ['price_option_id' => $this->adult->id, 'quantity' => 1],
        ['price_option_id' => $this->child->id, 'quantity' => 1],
    ]],
]);

test('the date must be within the event dates', function (): void {
    ($this->book)([['price_option_id' => $this->adult->id, 'quantity' => 1]], '2026-11-04');
})->throws(ReservationNotAllowedException::class, 'The selected date is outside the event dates.');

test('booking must be open', function (): void {
    $this->travelTo('2026-11-01 08:00:00');

    ($this->book)([['price_option_id' => $this->adult->id, 'quantity' => 1]]);
})->throws(ReservationNotAllowedException::class, 'Booking for this event is not open.');

test('the ticket option must belong to the event', function (): void {
    ($this->book)([['price_option_id' => 999_999, 'quantity' => 1]]);
})->throws(ReservationNotAllowedException::class, 'The selected option does not belong to this service.');

test('the daily ticket limit applies', function (): void {
    ReservationFactory::new()->forService($this->service)->at('2026-11-02')->state(['quantity' => 49])->create();

    ($this->book)([['price_option_id' => $this->adult->id, 'quantity' => 2]]);
})->throws(ReservationNotAllowedException::class, 'Not enough places left: 1 available.');
