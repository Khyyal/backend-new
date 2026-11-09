<?php

namespace Modules\Reservations\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Notifications\ReservationNotification;
use Modules\Support\Notifications\Contracts\NotificationServiceContract;

/**
 * Reminds clients of confirmed reservations starting within the reminder
 * window. Each reservation is claimed by stamping reminder_sent_at before
 * notifying, so overlapping runs never remind twice; rescheduling clears it.
 */
class SendReservationRemindersCommand extends Command
{
    protected $signature = 'reservations:send-reminders';

    protected $description = 'Remind clients of confirmed reservations starting soon';

    public function handle(NotificationServiceContract $notifications): int
    {
        $now = now()->toImmutable();
        $windowEnd = $now->addHours((int) config('reservations.reminder_hours_before'));
        $sent = 0;

        Reservation::query()
            ->where('status', ReservationStatus::Confirmed)
            ->whereNull('reminder_sent_at')
            ->whereBetween('date', [$now->toDateString(), $windowEnd->toDateString()])
            ->with('client')
            ->chunkById(100, function (Collection $reservations) use ($notifications, $now, $windowEnd, &$sent): void {
                foreach ($reservations as $reservation) {
                    $startsAt = $reservation->startsAt();

                    if ($startsAt->lte($now) || $startsAt->gt($windowEnd)) {
                        continue;
                    }

                    if ($this->remind($reservation, $notifications)) {
                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} reservation reminder(s).");

        return self::SUCCESS;
    }

    private function remind(Reservation $reservation, NotificationServiceContract $notifications): bool
    {
        $claimed = Reservation::query()
            ->whereKey($reservation->getKey())
            ->where('status', ReservationStatus::Confirmed)
            ->whereNull('reminder_sent_at')
            ->update(['reminder_sent_at' => now()]);

        if ($claimed !== 1 || $reservation->client === null) {
            return false;
        }

        $notifications->send($reservation->client, new ReservationNotification($reservation, 'reminder'));

        return true;
    }
}
