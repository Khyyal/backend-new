<?php

namespace Modules\Reservations\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Modules\Centers\Models\User as CenterUser;
use Modules\Clients\Models\Client;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Events\ClientMembershipExpired;
use Modules\Reservations\Events\ClientPackageActivated;
use Modules\Reservations\Events\ClientPackageCancelled;
use Modules\Reservations\Events\ClientPackageExhausted;
use Modules\Reservations\Events\ClientPackageExpired;
use Modules\Reservations\Events\ReservationCancelled;
use Modules\Reservations\Events\ReservationCompleted;
use Modules\Reservations\Events\ReservationConfirmed;
use Modules\Reservations\Events\ReservationCreated;
use Modules\Reservations\Events\ReservationMarkedNoShow;
use Modules\Reservations\Events\ReservationRescheduled;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Notifications\ClientMembershipNotification;
use Modules\Reservations\Notifications\ClientPackageActivatedNotification;
use Modules\Reservations\Notifications\ClientPackageNotification;
use Modules\Reservations\Notifications\ReservationNotification;
use Modules\Support\Notifications\Contracts\NotificationServiceContract;

final class SendReservationNotifications implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(
        private readonly NotificationServiceContract $notifications,
    ) {}

    /**
     * A booking made by the client goes to the center staff; one made by the center for a client goes to that client.
     */
    public function handleCreated(ReservationCreated $event): void
    {
        $reservation = $event->reservation;

        if ($reservation->source === ReservationSource::Center) {
            $this->notifyClient($reservation, 'created_by_center');

            return;
        }

        $this->notifyCenter($reservation, 'created');
    }

    public function handleConfirmed(ReservationConfirmed $event): void
    {
        $this->notifyClient($event->reservation, 'confirmed');
    }

    public function handleRescheduled(ReservationRescheduled $event): void
    {
        $this->notifyClient($event->reservation, 'rescheduled');
    }

    public function handleCompleted(ReservationCompleted $event): void
    {
        $this->notifyClient($event->reservation, 'completed');
    }

    public function handleNoShow(ReservationMarkedNoShow $event): void
    {
        $this->notifyClient($event->reservation, 'no_show');
    }

    /**
     * Tell the side that did not cancel; system cancellations (e.g. unpaid release) go to the client.
     */
    public function handleCancelled(ReservationCancelled $event): void
    {
        $reservation = $event->reservation;

        $canceller = $reservation->cancelledBy;

        match (true) {
            $canceller instanceof Client => $this->notifyCenter($reservation, 'cancelled_by_client'),
            $canceller instanceof CenterUser => $this->notifyClient($reservation, 'cancelled_by_center'),
            default => $this->notifyClient($reservation, 'cancelled'),
        };
    }

    public function handlePackageActivated(ClientPackageActivated $event): void
    {
        $client = $event->clientPackage->client;

        if ($client !== null) {
            $this->notifications->send($client, new ClientPackageActivatedNotification($event->clientPackage));
        }
    }

    public function handlePackageExhausted(ClientPackageExhausted $event): void
    {
        $this->notifyPackageClient($event->clientPackage, 'exhausted');
    }

    /**
     * Packages of a membership expire with it, so only the membership notice is sent.
     */
    public function handlePackageExpired(ClientPackageExpired $event): void
    {
        if ($event->clientPackage->client_membership_id === null) {
            $this->notifyPackageClient($event->clientPackage, 'expired');
        }
    }

    public function handlePackageCancelled(ClientPackageCancelled $event): void
    {
        $this->notifyPackageClient($event->clientPackage, 'cancelled');
    }

    public function handleMembershipExpired(ClientMembershipExpired $event): void
    {
        $client = $event->clientMembership->client;

        if ($client !== null) {
            $this->notifications->send($client, new ClientMembershipNotification($event->clientMembership, 'expired'));
        }
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            ReservationCreated::class => 'handleCreated',
            ReservationConfirmed::class => 'handleConfirmed',
            ReservationRescheduled::class => 'handleRescheduled',
            ReservationCompleted::class => 'handleCompleted',
            ReservationMarkedNoShow::class => 'handleNoShow',
            ReservationCancelled::class => 'handleCancelled',
            ClientPackageActivated::class => 'handlePackageActivated',
            ClientPackageExhausted::class => 'handlePackageExhausted',
            ClientPackageExpired::class => 'handlePackageExpired',
            ClientPackageCancelled::class => 'handlePackageCancelled',
            ClientMembershipExpired::class => 'handleMembershipExpired',
        ];
    }

    private function notifyClient(Reservation $reservation, string $event): void
    {
        $client = $reservation->client;

        if ($client !== null) {
            $this->notifications->send($client, new ReservationNotification($reservation, $event));
        }
    }

    private function notifyCenter(Reservation $reservation, string $event): void
    {
        $users = $reservation->center?->notifiableUsers();

        if ($users !== null && $users->isNotEmpty()) {
            $this->notifications->send($users, new ReservationNotification($reservation, $event));
        }
    }

    private function notifyPackageClient(ClientPackage $package, string $event): void
    {
        $client = $package->client;

        if ($client !== null) {
            $this->notifications->send($client, new ClientPackageNotification($package, $event));
        }
    }
}
