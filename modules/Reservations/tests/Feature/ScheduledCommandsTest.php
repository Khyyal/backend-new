<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Gateways\FakeGateway;
use Modules\Purchase\Managers\PaymentGatewayManager;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Reservations\Actions\CheckoutReservations;
use Modules\Reservations\Actions\PurchasePackage;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Models\ServicePackage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->client = ClientFactory::new()->create();
    $this->service = BookingFixtures::riding(CenterFactory::new()->create());

    $this->checkout = function (PaymentMethod $method = PaymentMethod::Online): array {
        $reservation = ReservationFactory::new()->forService($this->service)
            ->status(ReservationStatus::Pending)
            ->at('2026-10-26', '10:00:00', '11:00:00')
            ->create(['client_id' => $this->client->id, 'total_amount' => 100, 'subtotal' => 100]);
        $result = app(CheckoutReservations::class)->execute($this->client, [$reservation], PurchaseSource::Client, $method, 'fake');

        return [$reservation, $result];
    };
});

describe('reservations:expire-packages', function (): void {
    test('expires active packages past their date only', function (): void {
        $overdue = ClientPackageFactory::new()->create(['expires_at' => '2026-10-20 07:00:00']);
        $valid = ClientPackageFactory::new()->create(['expires_at' => '2026-10-21 00:00:00']);
        $exhausted = ClientPackageFactory::new()->create(['expires_at' => '2026-10-19 00:00:00', 'status' => ClientPackageStatus::Exhausted, 'remaining_sessions' => 0]);
        $pending = ClientPackageFactory::new()->create(['expires_at' => null, 'status' => ClientPackageStatus::Pending]);

        $this->artisan('reservations:expire-packages')
            ->expectsOutput('Expired 1 package(s).')
            ->assertSuccessful();

        expect($overdue->fresh()->status)->toBe(ClientPackageStatus::Expired)
            ->and($valid->fresh()->status)->toBe(ClientPackageStatus::Active)
            ->and($exhausted->fresh()->status)->toBe(ClientPackageStatus::Exhausted)
            ->and($pending->fresh()->status)->toBe(ClientPackageStatus::Pending);
    });
});

describe('reservations:release-unpaid', function (): void {
    test('cancels online checkouts unpaid past the hold window', function (): void {
        config(['reservations.unpaid_hold_minutes' => 30]);
        [$stale, $staleCheckout] = ($this->checkout)();

        $this->travelTo('2026-10-20 08:20:00');
        [$recent] = ($this->checkout)();

        $this->travelTo('2026-10-20 08:31:00');
        $this->artisan('reservations:release-unpaid')
            ->expectsOutput('Released 1 unpaid checkout(s).')
            ->assertSuccessful();

        expect($stale->fresh()->status)->toBe(ReservationStatus::Cancelled)
            ->and($staleCheckout->purchase->fresh()->status)->toBe(PurchaseStatus::Cancelled)
            ->and($staleCheckout->payment->fresh()->status)->toBe(PaymentStatus::Pending)
            ->and($recent->fresh()->status)->toBe(ReservationStatus::Pending);
    });

    test('keeps cash on arrival and checkouts with a payment in progress', function (): void {
        [$cash] = ($this->checkout)(PaymentMethod::CashOnArrival);
        [$processing, $processingCheckout] = ($this->checkout)();
        app(PaymentStateService::class)->markProcessing($processingCheckout->payment);

        $this->travelTo('2026-10-20 10:00:00');
        $this->artisan('reservations:release-unpaid')->expectsOutput('Released 0 unpaid checkout(s).');

        expect($cash->fresh()->status)->toBe(ReservationStatus::Confirmed)
            ->and($processing->fresh()->status)->toBe(ReservationStatus::Pending);
    });

    test('voids unpaid package purchases', function (): void {
        $servicePackage = ServicePackage::query()->create([
            'service_id' => $this->service->id,
            'price_option_id' => $this->service->priceOptions()->first()->id,
            'count' => 4,
            'price' => 300,
            'duration' => 1,
        ]);
        app(PurchasePackage::class)->execute($this->client, $servicePackage, PurchaseSource::Client, PaymentMethod::Online, 'fake');

        $this->travelTo('2026-10-20 10:00:00');
        $this->artisan('reservations:release-unpaid')->assertSuccessful();

        expect($this->client->packages()->sole()->status)->toBe(ClientPackageStatus::Cancelled);
    });

    test('a payment succeeding after the release is refunded', function (): void {
        [$reservation, $checkout] = ($this->checkout)();

        $this->travelTo('2026-10-20 10:00:00');
        $this->artisan('reservations:release-unpaid');

        app(PaymentStateService::class)->markSucceeded($checkout->payment);

        $payment = Payment::query()->find($checkout->payment->id);

        expect($payment->status)->toBe(PaymentStatus::Refunded)
            ->and((float) $payment->refunded_amount)->toBe(100.0)
            ->and($checkout->purchase->fresh()->status)->toBe(PurchaseStatus::Cancelled)
            ->and($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled);
    });

    test('a declined late refund is logged and leaves the payment succeeded', function (): void {
        app(PaymentGatewayManager::class)->extend('declining', fn () => new class extends FakeGateway
        {
            public function refund(Payment $payment, $amount): bool
            {
                return false;
            }
        });
        [, $checkout] = ($this->checkout)();
        $checkout->payment->update(['payment_company' => 'declining']);

        $this->travelTo('2026-10-20 10:00:00');
        $this->artisan('reservations:release-unpaid');

        Log::spy();
        app(PaymentStateService::class)->markSucceeded($checkout->payment);

        expect($checkout->payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
        Log::shouldHaveReceived('error')->once();
    });
});
