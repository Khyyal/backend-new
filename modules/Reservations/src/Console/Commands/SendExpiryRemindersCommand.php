<?php

namespace Modules\Reservations\Console\Commands;

use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Models\ClientMembership;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Notifications\ClientMembershipNotification;
use Modules\Reservations\Notifications\ClientPackageNotification;
use Modules\Support\Notifications\Contracts\NotificationServiceContract;

/**
 * Warns clients that an active package or membership expires within the
 * reminder window. Packages of a membership expire with it, so only the
 * membership is reminded. Each record is claimed by stamping
 * expiry_reminder_sent_at before notifying, so it is reminded once.
 */
class SendExpiryRemindersCommand extends Command
{
    protected $signature = 'reservations:send-expiry-reminders';

    protected $description = 'Remind clients of active packages and memberships expiring soon';

    public function handle(NotificationServiceContract $notifications): int
    {
        $windowEnd = now()->addDays((int) config('reservations.expiry_reminder_days_before'));

        $packages = $this->remind(
            fn (): Builder => ClientPackage::query()->where('status', ClientPackageStatus::Active)->whereNull('client_membership_id'),
            $windowEnd,
            fn (ClientPackage $package) => $notifications->send($package->client, new ClientPackageNotification($package, 'expiring')),
        );

        $memberships = $this->remind(
            fn (): Builder => ClientMembership::query()->where('status', ClientMembershipStatus::Active),
            $windowEnd,
            fn (ClientMembership $membership) => $notifications->send($membership->client, new ClientMembershipNotification($membership, 'expiring')),
        );

        $this->info("Sent {$packages} package and {$memberships} membership expiry reminder(s).");

        return self::SUCCESS;
    }

    /**
     * @template TModel of ClientPackage|ClientMembership
     *
     * @param  callable(): Builder<TModel>  $activeQuery
     * @param  callable(TModel): void  $send
     */
    private function remind(callable $activeQuery, DateTimeInterface $windowEnd, callable $send): int
    {
        $sent = 0;

        $activeQuery()
            ->whereNull('expiry_reminder_sent_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', $windowEnd)
            ->with('client')
            ->chunkById(100, function (Collection $records) use ($activeQuery, $send, &$sent): void {
                foreach ($records as $record) {
                    if ($record->client !== null && $this->claim($activeQuery, $record)) {
                        $send($record);
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    /**
     * Stamps the reminder only if the record is still active and unreminded.
     *
     * @param  callable(): Builder<ClientPackage|ClientMembership>  $activeQuery
     */
    private function claim(callable $activeQuery, Model $record): bool
    {
        return $activeQuery()
            ->whereKey($record->getKey())
            ->whereNull('expiry_reminder_sent_at')
            ->update(['expiry_reminder_sent_at' => now()]) === 1;
    }
}
