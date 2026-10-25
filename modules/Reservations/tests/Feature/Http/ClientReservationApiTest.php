<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Enums\PeriodGender;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Enums\VisitEnterType;
use Modules\Services\Models\ServiceBlock;
use Modules\Services\Models\Visit;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->client = ClientFactory::new()->create();
    $this->service = BookingFixtures::riding($this->center, maxTicketsPerHour: 3);
    $this->option = $this->service->priceOptions()->first();

    $this->body = fn (array $overrides = []) => [
        'service_id' => $this->service->id,
        'date' => '2026-10-26',
        'start_time' => '10:00',
        'price_option_id' => $this->option->id,
        'payment_method' => 'online',
        ...$overrides,
    ];
});

test('reservation endpoints require a client token', function (string $method, string $uri): void {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/v1/clients/reservations'],
    ['POST', '/api/v1/clients/reservations'],
    ['POST', '/api/v1/clients/reservations/1/cancel'],
    ['GET', '/api/v1/clients/packages'],
    ['GET', '/api/v1/clients/services/1/availability'],
]);

test('books online and returns the gateway checkout', function (): void {
    $response = $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/reservations', ($this->body)(['quantity' => 2]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.start_time', '10:00')
        ->assertJsonPath('data.end_time', '11:00')
        ->assertJsonPath('data.total_amount', 200)
        ->assertJsonPath('data.service.id', $this->service->id)
        ->assertJsonPath('checkout.purchase.status', 'pending')
        ->assertJsonPath('checkout.payment.method', 'online')
        ->assertJsonPath('checkout.payment.payment_company', 'fake');

    expect($response->json('checkout.gateway.checkout_url'))->toStartWith('https://fake.example/checkout/');

    app(PaymentStateService::class)->markSucceeded(Payment::query()->sole());

    $this->actingAs($this->client, 'client')
        ->getJson('/api/v1/clients/reservations/'.$response->json('data.id'))
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');
});

test('cash on arrival confirms the booking at once', function (): void {
    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/reservations', ($this->body)(['payment_method' => 'cash_on_arrival']))
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('checkout.payment.status', 'pending')
        ->assertJsonPath('checkout.gateway', null);
});

test('a client cannot record a manual payment', function (): void {
    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/reservations', ($this->body)(['payment_method' => 'manual']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payment_method');
});

test('validates the shape of the booking', function (array $overrides, string $field): void {
    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/reservations', ($this->body)($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'bad date' => [['date' => '26/10/2026'], 'date'],
    'bad time' => [['start_time' => '10am'], 'start_time'],
    'zero quantity' => [['quantity' => 0], 'quantity'],
    'end before start' => [['end_date' => '2026-10-25'], 'end_date'],
    'unknown gateway' => [['payment_company' => 'paypal'], 'payment_company'],
]);

test('a broken booking rule is a 422 with its reason and books nothing', function (): void {
    ReservationFactory::new()->forService($this->service)->at('2026-10-26', '10:00:00', '11:00:00')->create(['quantity' => 3]);

    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/reservations', ($this->body)())
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'capacity_exceeded')
        ->assertJsonPath('errors.reservation.0', 'Not enough places left: 0 available.');

    expect($this->client->reservations()->count())->toBe(0);
});

test('an unknown service is a 404', function (): void {
    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/reservations', ($this->body)(['service_id' => 999_999]))
        ->assertNotFound();
});

test('lists only my reservations, filtered by status', function (): void {
    $mine = ReservationFactory::new()->forService($this->service)->create(['client_id' => $this->client->id]);
    ReservationFactory::new()->forService($this->service)->status(ReservationStatus::Cancelled)->create(['client_id' => $this->client->id]);
    ReservationFactory::new()->forService($this->service)->create();

    $this->actingAs($this->client, 'client')
        ->getJson('/api/v1/clients/reservations?status=confirmed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});

test('another client\'s reservation is not found', function (string $method, string $suffix): void {
    $other = ReservationFactory::new()->forService($this->service)->create();

    $this->actingAs($this->client, 'client')
        ->json($method, "/api/v1/clients/reservations/{$other->id}{$suffix}")
        ->assertNotFound();
})->with([['GET', ''], ['POST', '/cancel']]);

test('cancels my reservation before the cutoff', function (): void {
    $reservation = ReservationFactory::new()->forService($this->service)->at('2026-10-26', '10:00:00', '11:00:00')
        ->create(['client_id' => $this->client->id]);

    $this->actingAs($this->client, 'client')
        ->postJson("/api/v1/clients/reservations/{$reservation->id}/cancel", ['reason' => 'Plans changed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancellation_reason', 'Plans changed');
});

test('cannot cancel inside the cutoff', function (): void {
    $this->travelTo('2026-10-26 08:00:00');
    $reservation = ReservationFactory::new()->forService($this->service)->at('2026-10-26', '10:00:00', '11:00:00')
        ->create(['client_id' => $this->client->id]);

    $this->actingAs($this->client, 'client')
        ->postJson("/api/v1/clients/reservations/{$reservation->id}/cancel")
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'cancellation_cutoff_passed');
});

describe('availability', function (): void {
    test('shows windows, booked slots, blocks and periods per day', function (): void {
        ReservationFactory::new()->forService($this->service)->at('2026-10-26', '10:00:00', '11:00:00')->create(['quantity' => 2]);
        ReservationFactory::new()->forService($this->service)->at('2026-10-26', '10:00:00', '11:00:00')->create(['quantity' => 1]);
        $this->service->periods()->create(['gender' => PeriodGender::Female, 'day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '10:00']);
        ServiceBlock::query()->create([
            'reason' => 'Holiday',
            'center_id' => $this->center->id,
            'service_id' => $this->service->id,
            'start_date' => '2026-10-27',
            'end_date' => '2026-10-27',
            'time_scope' => BlockTimeScope::AllDay,
        ]);

        $this->actingAs($this->client, 'client')
            ->getJson("/api/v1/clients/services/{$this->service->id}/availability?from=2026-10-26&to=2026-10-27")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.day_of_week', 1)
            ->assertJsonPath('data.0.capacity_per_hour', 3)
            ->assertJsonPath('data.0.windows', [['start' => '09:00', 'end' => '12:00']])
            ->assertJsonPath('data.0.booked', [['start' => '10:00', 'end' => '11:00', 'quantity' => 3]])
            ->assertJsonPath('data.0.periods', [['gender' => 'female', 'start' => '09:00', 'end' => '10:00']])
            ->assertJsonPath('data.0.blocked', false)
            ->assertJsonPath('data.1.blocked', true)
            ->assertJsonPath('data.1.windows', []);
    });

    test('the range is limited', function (): void {
        $this->actingAs($this->client, 'client')
            ->getJson("/api/v1/clients/services/{$this->service->id}/availability?from=2026-10-01&to=2026-11-15")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    });
});

test('a free booking is confirmed without a checkout', function (): void {
    $visit = Visit::query()->create(['enter_type' => VisitEnterType::AllDay, 'max_tickets_per_day' => 10]);
    $service = BookingFixtures::service($this->center, $visit, ServiceType::Visit);
    $service->priceOptions()->create(['name' => null, 'price' => 0]);
    BookingFixtures::schedule($visit, 1, null, null);

    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/reservations', ['service_id' => $service->id, 'date' => '2026-10-26', 'payment_method' => 'online'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('checkout', null);
});
