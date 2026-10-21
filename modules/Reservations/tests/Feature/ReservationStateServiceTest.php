<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Gateways\FakeGateway;
use Modules\Purchase\Managers\PaymentGatewayManager;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Purchase\Services\PurchaseStateService;
use Modules\Reservations\Actions\BookFromPackage;
use Modules\Reservations\Actions\CheckoutReservations;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Events\ReservationCancelled;
use Modules\Reservations\Events\ReservationRescheduled;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Services\ReservationStateService;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Resort;
use Modules\Services\Models\ServicePackage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->client = ClientFactory::new()->create();
    $this->service = BookingFixtures::riding($this->center, maxTicketsPerHour: 2);
    $this->state = app(ReservationStateService::class);

    $this->reservation = fn (array $attributes = []) => ReservationFactory::new()->forService($this->service)
        ->at('2026-10-26', '10:00:00', '11:00:00')
        ->create([
            'client_id' => $this->client->id,
            'price_option_id' => $this->service->priceOptions()->first()->id,
            'unit_price' => 100,
            'subtotal' => 100,
            'total_amount' => 100,
            ...$attributes,
        ]);

    $this->paidOnline = function () {
        $reservation = ($this->reservation)(['status' => ReservationStatus::Pending]);
        $result = app(CheckoutReservations::class)->execute($this->client, [$reservation], PurchaseSource::Client, PaymentMethod::Online, 'fake');
        app(PaymentStateService::class)->markSucceeded($result->payment);

        return [$reservation->fresh(), $result->payment];
    };
});

test('only pending reservations can be confirmed', function (): void {
    $reservation = ($this->reservation)(['status' => ReservationStatus::Pending]);

    expect($this->state->confirm($reservation)->status)->toBe(ReservationStatus::Confirmed)
        ->and(fn () => $this->state->confirm($reservation))
        ->toThrow(ReservationNotAllowedException::class, 'A confirmed reservation cannot be confirmed.');
});

describe('cancel', function (): void {
    test('records who cancelled and why', function (): void {
        Event::fake([ReservationCancelled::class]);
        $staff = UserFactory::new()->create();

        $cancelled = $this->state->cancel(($this->reservation)(), $staff, 'Horse is sick');

        expect($cancelled->status)->toBe(ReservationStatus::Cancelled)
            ->and($cancelled->cancelled_at)->not->toBeNull()
            ->and($cancelled->cancelledBy->is($staff))->toBeTrue()
            ->and($cancelled->cancellation_reason)->toBe('Horse is sick');

        Event::assertDispatched(ReservationCancelled::class);
    });

    test('a client cannot cancel inside the cutoff, the center can', function (): void {
        config(['reservations.client_cancellation_cutoff_hours' => 24]);
        $this->travelTo('2026-10-25 11:00:00');
        $reservation = ($this->reservation)();

        expect(fn () => $this->state->cancel($reservation, $this->client))
            ->toThrow(ReservationNotAllowedException::class, 'Reservations can only be cancelled up to 24 hours before they start.')
            ->and($this->state->cancel($reservation, UserFactory::new()->create())->status)->toBe(ReservationStatus::Cancelled);
    });

    test('a client can cancel before the cutoff', function (): void {
        $this->travelTo('2026-10-25 09:59:00');

        expect($this->state->cancel(($this->reservation)(), $this->client)->status)->toBe(ReservationStatus::Cancelled);
    });

    test('finished reservations cannot be cancelled', function (): void {
        $this->state->cancel(($this->reservation)(['status' => ReservationStatus::Completed]));
    })->throws(ReservationNotAllowedException::class, 'A completed reservation cannot be cancelled.');

    test('gives the package credit back and reactivates an exhausted package', function (): void {
        $servicePackage = ServicePackage::query()->create([
            'service_id' => $this->service->id,
            'price_option_id' => $this->service->priceOptions()->first()->id,
            'count' => 1,
            'price' => 90,
            'duration' => 1,
        ]);
        $package = ClientPackageFactory::new()->forServicePackage($servicePackage)->create(['client_id' => $this->client->id]);
        $reservation = app(BookFromPackage::class)->execute($package, CarbonImmutable::parse('2026-10-26'), '10:00', ReservationSource::Client);

        expect($package->fresh()->status)->toBe(ClientPackageStatus::Exhausted);

        $this->state->cancel($reservation, $this->client);

        expect($package->fresh()->remaining_sessions)->toBe(1)
            ->and($package->fresh()->status)->toBe(ClientPackageStatus::Active);
    });

    test('refunds an online payment through its gateway', function (): void {
        [$reservation, $payment] = ($this->paidOnline)();

        $cancelled = $this->state->cancel($reservation, $this->client);

        expect($cancelled->metadata['refund'])->toMatchArray(['amount' => 100.0, 'status' => 'refunded', 'payment_id' => $payment->id])
            ->and($payment->fresh()->metadata['refunds'][0])->toMatchArray(['reservation_id' => $reservation->id, 'amount' => 100.0]);
    });

    test('a failed refund leaves the reservation untouched', function (): void {
        app(PaymentGatewayManager::class)->extend('failing', fn () => new class extends FakeGateway
        {
            public function refund(Payment $payment, $amount): bool
            {
                return false;
            }
        });
        [$reservation, $payment] = ($this->paidOnline)();
        $payment->update(['payment_company' => 'failing']);

        expect(fn () => $this->state->cancel($reservation, $this->client))
            ->toThrow(ReservationNotAllowedException::class, 'The payment could not be refunded; the reservation was not cancelled.')
            ->and($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
    });

    test('a manual payment is flagged for the center to refund', function (): void {
        $reservation = ($this->reservation)(['status' => ReservationStatus::Pending]);
        app(CheckoutReservations::class)->execute($this->client, [$reservation], PurchaseSource::Center, PaymentMethod::Manual);

        $cancelled = $this->state->cancel($reservation->fresh(), UserFactory::new()->create());

        expect($cancelled->metadata['refund'])->toMatchArray(['status' => 'manual_required', 'method' => 'manual']);
    });

    test('nothing is refunded for an unpaid cash on arrival reservation', function (): void {
        $reservation = ($this->reservation)(['status' => ReservationStatus::Pending]);
        $result = app(CheckoutReservations::class)->execute($this->client, [$reservation], PurchaseSource::Client, PaymentMethod::CashOnArrival);

        $cancelled = $this->state->cancel($reservation->fresh(), $this->client);

        expect($cancelled->metadata['refund'] ?? null)->toBeNull()
            ->and($result->payment->fresh()->status)->toBe(PaymentStatus::Pending);
    });
});

describe('attendance', function (): void {
    test('cannot be recorded before the reservation starts', function (string $method): void {
        $this->travelTo('2026-10-26 09:59:00');

        expect(fn () => $this->state->{$method}(($this->reservation)()))
            ->toThrow(ReservationNotAllowedException::class, 'The reservation has not started yet.');
    })->with(['complete', 'markNoShow']);

    test('marks a started reservation completed or no-show', function (string $method, ReservationStatus $status): void {
        $this->travelTo('2026-10-26 10:00:00');

        expect($this->state->{$method}(($this->reservation)())->status)->toBe($status);
    })->with([
        ['complete', ReservationStatus::Completed],
        ['markNoShow', ReservationStatus::NoShow],
    ]);

    test('only confirmed reservations can be completed', function (): void {
        $this->travelTo('2026-10-26 12:00:00');

        $this->state->complete(($this->reservation)(['status' => ReservationStatus::Pending]));
    })->throws(ReservationNotAllowedException::class, 'A pending reservation cannot be completed.');
});

describe('reschedule', function (): void {
    test('moves to a free slot and keeps the previous one in history', function (): void {
        Event::fake([ReservationRescheduled::class]);
        $reservation = ($this->reservation)();

        $moved = $this->state->reschedule($reservation, CarbonImmutable::parse('2026-11-02'), '09:00');

        expect($moved->date->toDateString())->toBe('2026-11-02')
            ->and($moved->start_time)->toBe('09:00:00')
            ->and($moved->end_time)->toBe('10:00:00')
            ->and($moved->metadata['reschedules'][0])->toMatchArray(['date' => '2026-10-26', 'start_time' => '10:00:00']);

        Event::assertDispatched(ReservationRescheduled::class);
    });

    test('can move within its own slot without counting itself', function (): void {
        $reservation = ($this->reservation)(['quantity' => 2, 'subtotal' => 200, 'total_amount' => 200]);

        expect($this->state->reschedule($reservation, CarbonImmutable::parse('2026-10-26'), '10:30')->start_time)->toBe('10:30:00');
    });

    test('the new slot must have room', function (): void {
        ReservationFactory::new()->forService($this->service)->at('2026-11-02', '09:00:00', '10:00:00')->create(['quantity' => 2]);

        $this->state->reschedule(($this->reservation)(), CarbonImmutable::parse('2026-11-02'), '09:00');
    })->throws(ReservationNotAllowedException::class, 'Not enough places left: 0 available.');

    test('a package reservation can move even after the package is exhausted', function (): void {
        $package = ClientPackageFactory::new()->create([
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'price_option_id' => $this->service->priceOptions()->first()->id,
            'remaining_sessions' => 0,
            'status' => ClientPackageStatus::Exhausted,
        ]);
        $reservation = ($this->reservation)(['client_package_id' => $package->id, 'unit_price' => 0, 'subtotal' => 0, 'total_amount' => 0]);

        expect($this->state->reschedule($reservation, CarbonImmutable::parse('2026-11-02'), '11:00')->start_time)->toBe('11:00:00');
    });

    test('a move to a differently priced stay is rejected', function (): void {
        $resort = Resort::query()->create();
        $service = BookingFixtures::service($this->center, $resort, ServiceType::Resort);
        $service->priceOptions()->create(['name' => null, 'quantity' => 4, 'price' => 500]);
        $service->priceOptions()->create(['name' => null, 'quantity' => 5, 'price' => 800]);
        $reservation = ReservationFactory::new()->forService($service)->create([
            'date' => '2026-10-29',
            'end_date' => '2026-10-29',
            'unit_price' => 500,
            'subtotal' => 500,
            'total_amount' => 500,
        ]);

        expect($this->state->reschedule($reservation, CarbonImmutable::parse('2026-11-05'))->date->toDateString())->toBe('2026-11-05')
            ->and(fn () => $this->state->reschedule($reservation, CarbonImmutable::parse('2026-11-06')))
            ->toThrow(ReservationNotAllowedException::class, 'The new date has a different price; cancel and book again instead.');
    });

    test('cancelled reservations cannot be moved', function (): void {
        $this->state->reschedule(($this->reservation)(['status' => ReservationStatus::Cancelled]), CarbonImmutable::parse('2026-11-02'), '09:00');
    })->throws(ReservationNotAllowedException::class, 'A cancelled reservation cannot be rescheduled.');
});

test('every reservation of a confirmed purchase is confirmed once', function (): void {
    $reservation = ($this->reservation)(['status' => ReservationStatus::Pending]);
    $result = app(CheckoutReservations::class)->execute($this->client, [$reservation], PurchaseSource::Client, PaymentMethod::Online, 'fake');

    app(PurchaseStateService::class)->confirm($result->purchase);

    expect(Reservation::query()->where('status', ReservationStatus::Confirmed)->count())->toBe(1);
});
