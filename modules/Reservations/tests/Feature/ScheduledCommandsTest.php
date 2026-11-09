<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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
use Modules\Reservations\Database\Factories\ClientMembershipFactory;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Events\ClientMembershipExpired;
use Modules\Reservations\Events\ClientPackageExpired;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Notifications\ClientMembershipNotification;
use Modules\Reservations\Notifications\ClientPackageNotification;
use Modules\Reservations\Notifications\ReservationNotification;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Models\ServicePackage;
use Spatie\Activitylog\Models\Activity;
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
            ->expectsOutput('Expired 1 package(s) and 0 membership(s).')
            ->assertSuccessful();

        expect($overdue->fresh()->status)->toBe(ClientPackageStatus::Expired)
            ->and($valid->fresh()->status)->toBe(ClientPackageStatus::Active)
            ->and($exhausted->fresh()->status)->toBe(ClientPackageStatus::Exhausted)
            ->and($pending->fresh()->status)->toBe(ClientPackageStatus::Pending);
    });

    test('fires an expired event for each expired package and membership', function (): void {
        Event::fake([ClientPackageExpired::class, ClientMembershipExpired::class]);
        $package = ClientPackageFactory::new()->expired()->create();
        $membership = ClientMembershipFactory::new()->expired()->create();

        $this->artisan('reservations:expire-packages')->assertSuccessful();

        Event::assertDispatched(ClientPackageExpired::class, fn (ClientPackageExpired $event): bool => $event->clientPackage->is($package));
        Event::assertDispatched(ClientMembershipExpired::class, fn (ClientMembershipExpired $event): bool => $event->clientMembership->is($membership));
    });

    test('audits each expired package and membership', function (): void {
        $package = ClientPackageFactory::new()->expired()->create();
        $membership = ClientMembershipFactory::new()->expired()->create();

        $this->artisan('reservations:expire-packages')->assertSuccessful();

        foreach ([$package, $membership] as $model) {
            $log = Activity::query()->forSubject($model)->forEvent('updated')->sole();

            expect($log->attribute_changes['attributes'])->toBe(['status' => 'expired']);
        }
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
            ->and((int) $payment->refunded_amount)->toBe(100)
            ->and($checkout->purchase->fresh()->status)->toBe(PurchaseStatus::Cancelled)
            ->and($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled);
    });

    test('a declined late refund is logged and leaves the payment succeeded', function (): void {
        app(PaymentGatewayManager::class)->extend('declining', fn () => new class extends FakeGateway
        {
            public function refund(Payment $payment, int $amount): bool
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

describe('reservations:send-reminders', function (): void {
    beforeEach(function (): void {
        Notification::fake();

        $this->booking = fn (string $date, ?string $startTime, ReservationStatus $status = ReservationStatus::Confirmed, array $attributes = []): Reservation => ReservationFactory::new()->forService($this->service)
            ->status($status)
            ->at($date, $startTime)
            ->create(['client_id' => $this->client->id, ...$attributes]);

        $this->assertReminded = function (Reservation $reservation): void {
            Notification::assertSentTo($this->client, ReservationNotification::class, fn (ReservationNotification $notification): bool => $notification->type() === 'reservation_reminder'
                && $notification->reservation->is($reservation));
        };
    });

    test('reminds confirmed reservations starting within four hours only', function (): void {
        $soon = ($this->booking)('2026-10-20', '12:00:00');
        $later = ($this->booking)('2026-10-20', '12:30:00');
        $started = ($this->booking)('2026-10-20', '08:00:00');

        $this->artisan('reservations:send-reminders')
            ->expectsOutput('Sent 1 reservation reminder(s).')
            ->assertSuccessful();

        ($this->assertReminded)($soon);
        Notification::assertSentTimes(ReservationNotification::class, 1);
        expect($soon->fresh()->reminder_sent_at)->not->toBeNull()
            ->and($later->fresh()->reminder_sent_at)->toBeNull()
            ->and($started->fresh()->reminder_sent_at)->toBeNull();
    });

    test('skips pending, cancelled and already reminded reservations', function (): void {
        ($this->booking)('2026-10-20', '10:00:00', ReservationStatus::Pending);
        ($this->booking)('2026-10-20', '10:00:00', ReservationStatus::Cancelled);
        ($this->booking)('2026-10-20', '10:00:00', attributes: ['reminder_sent_at' => now()->subHour()]);

        $this->artisan('reservations:send-reminders')->expectsOutput('Sent 0 reservation reminder(s).');

        Notification::assertNothingSent();
    });

    test('reminds each reservation once across runs', function (): void {
        ($this->booking)('2026-10-20', '10:00:00');

        $this->artisan('reservations:send-reminders');
        $this->travel(5)->minutes();
        $this->artisan('reservations:send-reminders')->expectsOutput('Sent 0 reservation reminder(s).');

        Notification::assertSentTimes(ReservationNotification::class, 1);
    });

    test('treats a date-only reservation as starting at midnight', function (): void {
        $reservation = ($this->booking)('2026-10-21', null);

        $this->travelTo('2026-10-20 19:55:00');
        $this->artisan('reservations:send-reminders')->expectsOutput('Sent 0 reservation reminder(s).');

        $this->travelTo('2026-10-20 20:00:00');
        $this->artisan('reservations:send-reminders')->expectsOutput('Sent 1 reservation reminder(s).');

        ($this->assertReminded)($reservation);
    });
});

describe('reservations:send-expiry-reminders', function (): void {
    beforeEach(function (): void {
        Notification::fake();
    });

    test('reminds active packages and memberships expiring within three days once', function (): void {
        $package = ClientPackageFactory::new()->create(['client_id' => $this->client->id, 'expires_at' => now()->addDays(3)]);
        $membership = ClientMembershipFactory::new()->create(['client_id' => $this->client->id, 'expires_at' => now()->addDays(2)]);

        $this->artisan('reservations:send-expiry-reminders')
            ->expectsOutput('Sent 1 package and 1 membership expiry reminder(s).')
            ->assertSuccessful();
        $this->artisan('reservations:send-expiry-reminders')
            ->expectsOutput('Sent 0 package and 0 membership expiry reminder(s).');

        Notification::assertSentToTimes($this->client, ClientPackageNotification::class, 1);
        Notification::assertSentTo($this->client, ClientPackageNotification::class, fn (ClientPackageNotification $notification): bool => $notification->type() === 'client_package_expiring'
            && $notification->package->is($package));
        Notification::assertSentTo($this->client, ClientMembershipNotification::class, fn (ClientMembershipNotification $notification): bool => $notification->type() === 'client_membership_expiring'
            && $notification->membership->is($membership));
    });

    test('skips packages and memberships outside the window, inactive, already reminded or part of a membership', function (): void {
        $membership = ClientMembershipFactory::new()->create(['expires_at' => now()->addDays(10)]);
        ClientPackageFactory::new()->create(['client_membership_id' => $membership->id, 'expires_at' => now()->addDay()]);
        ClientPackageFactory::new()->create(['expires_at' => now()->addDays(4)]);
        ClientPackageFactory::new()->expired()->create();
        ClientPackageFactory::new()->create(['expires_at' => now()->addDay(), 'status' => ClientPackageStatus::Exhausted, 'remaining_sessions' => 0]);
        ClientPackageFactory::new()->create(['expires_at' => now()->addDay(), 'expiry_reminder_sent_at' => now()->subDay()]);
        ClientMembershipFactory::new()->pending()->create();

        $this->artisan('reservations:send-expiry-reminders')
            ->expectsOutput('Sent 0 package and 0 membership expiry reminder(s).');

        Notification::assertNothingSent();
    });
});
