<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Purchase\Services\PurchaseStateService;
use Modules\Reservations\Actions\CheckoutReservations;
use Modules\Reservations\Actions\PurchasePackage;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Models\ServicePackage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->client = ClientFactory::new()->create();
    $this->service = BookingFixtures::riding($this->center);

    $this->pending = fn (array $attributes = []) => ReservationFactory::new()->forService($this->service)
        ->status(ReservationStatus::Pending)
        ->at('2026-10-26', '10:00:00', '11:00:00')
        ->create(['client_id' => $this->client->id, 'total_amount' => 150, 'subtotal' => 150, ...$attributes]);

    $this->checkout = fn (array $reservations, PaymentMethod $method, PurchaseSource $source = PurchaseSource::Client) => app(CheckoutReservations::class)
        ->execute($this->client, $reservations, $source, $method, 'fake');
});

describe('reservations', function (): void {
    test('an online checkout waits for the purchase to be confirmed', function (): void {
        $first = ($this->pending)();
        $second = ($this->pending)(['total_amount' => 50, 'subtotal' => 50]);

        $result = ($this->checkout)([$first, $second], PaymentMethod::Online);

        expect($result->purchase->status)->toBe(PurchaseStatus::Pending)
            ->and((float) $result->purchase->total_amount)->toBe(200.0)
            ->and($result->purchase->items)->toHaveCount(2)
            ->and($result->purchase->merchant_id)->toBe($this->center->id)
            ->and($result->payment->status)->toBe(PaymentStatus::Pending)
            ->and($result->payment->payment_company)->toBe('fake')
            ->and($first->fresh()->status)->toBe(ReservationStatus::Pending);

        app(PurchaseStateService::class)->confirm($result->purchase);

        expect($first->fresh()->status)->toBe(ReservationStatus::Confirmed)
            ->and($second->fresh()->status)->toBe(ReservationStatus::Confirmed);
    });

    test('cash on arrival confirms the reservation and leaves the payment pending', function (): void {
        $reservation = ($this->pending)();

        $result = ($this->checkout)([$reservation], PaymentMethod::CashOnArrival);

        expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed)
            ->and($result->payment->status)->toBe(PaymentStatus::Pending)
            ->and($result->purchase->status)->toBe(PurchaseStatus::Pending);
    });

    test('cash on arrival must be enabled for the service', function (): void {
        $this->service->settings()->where('key', 'enable_cash_on_arrival')->update(['value' => '0']);

        ($this->checkout)([($this->pending)()], PaymentMethod::CashOnArrival);
    })->throws(ReservationNotAllowedException::class, 'Cash on arrival is not available for this service.');

    test('a manual payment by the center confirms everything at once', function (): void {
        $reservation = ($this->pending)();

        $result = ($this->checkout)([$reservation], PaymentMethod::Manual, PurchaseSource::Center);

        expect($result->payment->status)->toBe(PaymentStatus::Succeeded)
            ->and($result->purchase->status)->toBe(PurchaseStatus::Confirmed)
            ->and($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
    });

    test('only the center can record a manual payment', function (): void {
        ($this->checkout)([($this->pending)()], PaymentMethod::Manual);
    })->throws(ReservationNotAllowedException::class, 'Only center staff can record a manual payment.');

    test('rejects reservations that cannot be checked out together', function (Closure $reservations): void {
        expect(fn () => ($this->checkout)($reservations->call($this), PaymentMethod::Online))
            ->toThrow(ReservationNotAllowedException::class, 'All reservations of a checkout must be pending');
    })->with([
        'none' => [fn () => []],
        'confirmed' => [fn () => [($this->pending)(['status' => ReservationStatus::Confirmed])]],
        'free' => [fn () => [($this->pending)(['total_amount' => 0, 'subtotal' => 0])]],
        'another client' => [fn () => [($this->pending)(['client_id' => ClientFactory::new()->create()->id])]],
        'another center' => [fn () => [
            ($this->pending)(),
            ReservationFactory::new()->status(ReservationStatus::Pending)->create(['client_id' => $this->client->id]),
        ]],
    ]);

    test('a reservation cannot be checked out twice', function (): void {
        $reservation = ($this->pending)();
        ($this->checkout)([$reservation], PaymentMethod::Online);

        ($this->checkout)([$reservation->fresh()], PaymentMethod::Online);
    })->throws(ReservationNotAllowedException::class, 'All reservations of a checkout must be pending');

    test('a cancelled purchase releases its pending reservations', function (): void {
        $reservation = ($this->pending)();
        $result = ($this->checkout)([$reservation], PaymentMethod::Online);

        app(PurchaseStateService::class)->cancel($result->purchase);

        expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
            ->and($reservation->fresh()->cancellation_reason)->toBe('purchase_cancelled');
    });
});

describe('packages', function (): void {
    beforeEach(function (): void {
        $this->servicePackage = ServicePackage::query()->create([
            'service_id' => $this->service->id,
            'price_option_id' => $this->service->priceOptions()->first()->id,
            'count' => 8,
            'price' => 700,
            'duration' => 3,
        ]);

        $this->purchasePackage = fn (PaymentMethod $method = PaymentMethod::Online, PurchaseSource $source = PurchaseSource::Client) => app(PurchasePackage::class)
            ->execute($this->client, $this->servicePackage, $source, $method, 'fake');
    });

    test('a package is pending until its purchase is confirmed', function (): void {
        $result = ($this->purchasePackage)();
        $package = ClientPackage::query()->sole();

        expect($package->status)->toBe(ClientPackageStatus::Pending)
            ->and($package->remaining_sessions)->toBe(8)
            ->and($package->expires_at)->toBeNull()
            ->and((float) $result->purchase->total_amount)->toBe(700.0);

        $this->travelTo('2026-10-22 08:00:00');
        app(PurchaseStateService::class)->confirm($result->purchase);

        expect($package->fresh()->status)->toBe(ClientPackageStatus::Active)
            ->and($package->fresh()->expires_at->toDateString())->toBe('2027-01-22');
    });

    test('a center selling at the desk activates the package at once', function (): void {
        ($this->purchasePackage)(PaymentMethod::Manual, PurchaseSource::Center);

        expect(ClientPackage::query()->sole()->status)->toBe(ClientPackageStatus::Active);
    });

    test('a cancelled purchase voids the pending package', function (): void {
        $result = ($this->purchasePackage)();

        app(PurchaseStateService::class)->cancel($result->purchase);

        expect(ClientPackage::query()->sole()->status)->toBe(ClientPackageStatus::Cancelled);
    });

    test('packages cannot be paid on arrival', function (): void {
        ($this->purchasePackage)(PaymentMethod::CashOnArrival);
    })->throws(ReservationNotAllowedException::class, 'Cash on arrival is not available for this service.');

    test('package booking must be enabled to sell a package', function (): void {
        $this->service->settings()->where('key', 'enable_package_booking')->update(['value' => '0']);

        expect(fn () => ($this->purchasePackage)())->toThrow(ReservationNotAllowedException::class, 'Package booking is not enabled for this service.')
            ->and(ClientPackage::query()->count())->toBe(0);
    });
});

test('a successful online payment confirms the reservations', function (): void {
    $reservation = ($this->pending)();
    $result = ($this->checkout)([$reservation], PaymentMethod::Online);

    app(PaymentStateService::class)->markSucceeded($result->payment);

    expect($result->purchase->fresh()->status)->toBe(PurchaseStatus::Confirmed)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});
