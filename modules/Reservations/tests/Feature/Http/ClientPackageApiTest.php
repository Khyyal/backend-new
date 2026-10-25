<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Models\ServicePackage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->client = ClientFactory::new()->create();
    $this->service = BookingFixtures::riding(CenterFactory::new()->create());
    $this->servicePackage = ServicePackage::query()->create([
        'service_id' => $this->service->id,
        'price_option_id' => $this->service->priceOptions()->first()->id,
        'count' => 4,
        'price' => 350,
        'duration' => 2,
    ]);
});

test('buys a package that activates once paid', function (): void {
    $response = $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/packages', ['service_package_id' => $this->servicePackage->id])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.total_sessions', 4)
        ->assertJsonPath('data.duration_minutes', 60)
        ->assertJsonPath('checkout.purchase.total_amount', 350)
        ->assertJsonPath('checkout.payment.method', 'online');

    app(PaymentStateService::class)->markSucceeded(Payment::query()->sole());

    $this->actingAs($this->client, 'client')
        ->getJson('/api/v1/clients/packages')
        ->assertOk()
        ->assertJsonPath('data.0.id', $response->json('data.id'))
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.0.is_usable', true)
        ->assertJsonPath('data.0.expires_at', '2026-12-20T08:00:00+00:00');
});

test('an unknown package is a validation error', function (): void {
    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/packages', ['service_package_id' => 999_999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service_package_id');
});

test('books a session from my package', function (): void {
    $package = ClientPackageFactory::new()->forServicePackage($this->servicePackage)->create(['client_id' => $this->client->id]);

    $this->actingAs($this->client, 'client')
        ->postJson("/api/v1/clients/packages/{$package->id}/reservations", ['date' => '2026-10-26', 'start_time' => '09:00'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.client_package_id', $package->id)
        ->assertJsonPath('data.total_amount', 0);

    expect($package->fresh()->remaining_sessions)->toBe(3);
});

test('a start time is required to book from a package', function (): void {
    $package = ClientPackageFactory::new()->forServicePackage($this->servicePackage)->create(['client_id' => $this->client->id]);

    $this->actingAs($this->client, 'client')
        ->postJson("/api/v1/clients/packages/{$package->id}/reservations", ['date' => '2026-10-26'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('start_time');
});

test('another client\'s package is not found', function (): void {
    $package = ClientPackageFactory::new()->forServicePackage($this->servicePackage)->create();

    $this->actingAs($this->client, 'client')
        ->postJson("/api/v1/clients/packages/{$package->id}/reservations", ['date' => '2026-10-26', 'start_time' => '09:00'])
        ->assertNotFound();
});
