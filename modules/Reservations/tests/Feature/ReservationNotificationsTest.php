<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Events\ClientPackageActivated;
use Modules\Reservations\Events\ReservationCreated;
use Modules\Reservations\Notifications\ClientPackageActivatedNotification;
use Modules\Reservations\Notifications\ReservationNotification;
use Modules\Reservations\Services\ReservationStateService;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');
    Notification::fake();

    $this->center = CenterFactory::new()->create();
    $this->client = ClientFactory::new()->create();
    $this->staff = UserFactory::new()->create();
    $this->inactiveStaff = UserFactory::new()->create();
    $this->center->users()->attach($this->staff, ['status' => ActivationStatus::ACTIVE->value, 'joined_at' => now()->toDateString()]);
    $this->center->users()->attach($this->inactiveStaff, ['status' => ActivationStatus::INACTIVE->value, 'joined_at' => now()->toDateString()]);

    $service = BookingFixtures::riding($this->center);
    $this->state = app(ReservationStateService::class);

    $this->reservation = fn (array $attributes = []) => ReservationFactory::new()->forService($service)
        ->at('2026-10-26', '10:00:00', '11:00:00')
        ->create([
            'client_id' => $this->client->id,
            'price_option_id' => $service->priceOptions()->first()->id,
            'status' => ReservationStatus::Confirmed,
            ...$attributes,
        ]);

    $this->assertSent = function (object $notifiable, string $type): void {
        Notification::assertSentTo($notifiable, ReservationNotification::class, fn (ReservationNotification $notification): bool => $notification->type() === $type);
    };
});

test('a client booking notifies active center staff only', function (): void {
    event(new ReservationCreated(($this->reservation)(['source' => ReservationSource::Client])));

    ($this->assertSent)($this->staff, 'reservation_created');
    Notification::assertNotSentTo([$this->inactiveStaff, $this->client], ReservationNotification::class);
});

test('a center booking notifies the client', function (): void {
    event(new ReservationCreated(($this->reservation)(['source' => ReservationSource::Center])));

    ($this->assertSent)($this->client, 'reservation_created_by_center');
    Notification::assertNotSentTo($this->staff, ReservationNotification::class);
});

test('status changes notify the client', function (string $action, string $type): void {
    $reservation = ($this->reservation)(['status' => $action === 'confirm' ? ReservationStatus::Pending : ReservationStatus::Confirmed]);

    $this->travelTo('2026-10-26 12:00:00');
    $this->state->{$action}($reservation);

    ($this->assertSent)($this->client, $type);
})->with([
    ['confirm', 'reservation_confirmed'],
    ['complete', 'reservation_completed'],
    ['markNoShow', 'reservation_no_show'],
]);

test('a cancellation notifies the other side, never the actor', function (): void {
    $this->state->cancel(($this->reservation)(), $this->staff);
    ($this->assertSent)($this->client, 'reservation_cancelled_by_center');
    Notification::assertNotSentTo($this->staff, ReservationNotification::class);

    $this->state->cancel(($this->reservation)(), $this->client);
    ($this->assertSent)($this->staff, 'reservation_cancelled_by_client');
});

test('an activated package notifies its client', function (): void {
    $package = ClientPackageFactory::new()->create(['client_id' => $this->client->id]);

    event(new ClientPackageActivated($package));

    Notification::assertSentTo($this->client, ClientPackageActivatedNotification::class);
});

test('reservation notification carries localized copy and deep-link data', function (): void {
    $notification = new ReservationNotification(($this->reservation)(), 'confirmed');
    $payload = $notification->toArray($this->client);
    $push = $notification->toFcm($this->client, 'ar');

    expect($payload['type'])->toBe('reservation_confirmed')
        ->and($payload['title'])->toBe(['en' => 'Reservation confirmed', 'ar' => 'تم تأكيد الحجز'])
        ->and($payload['body']['en'])->toContain('2026-10-26 at 10:00')
        ->and($push->notification->title)->toBe('تم تأكيد الحجز')
        ->and($push->data['reservation_id'])->toBe((string) $notification->reservation->id);
});
