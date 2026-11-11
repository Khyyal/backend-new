<?php

namespace Modules\Reservations\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Reservations\Enums\ClientMembershipLogType;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\ClientMembership;
use Modules\Reservations\Models\ClientMembershipLog;

/**
 * Records that a member showed up at the center. Every call adds a log entry;
 * it does not touch reservations.
 */
class CheckInClientMembership
{
    /**
     * @throws ReservationNotAllowedException
     */
    public function execute(ClientMembership $clientMembership, Model $actor, ?string $notes = null): ClientMembershipLog
    {
        return DB::transaction(function () use ($clientMembership, $actor, $notes): ClientMembershipLog {
            $membership = ClientMembership::query()->whereKey($clientMembership->getKey())->lockForUpdate()->firstOrFail();

            if (! $membership->isUsable()) {
                throw ReservationNotAllowedException::because('membership_not_usable');
            }

            return $membership->logs()->create([
                'type' => ClientMembershipLogType::CheckIn,
                'actor_type' => $actor->getMorphClass(),
                'actor_id' => $actor->getKey(),
                'notes' => $notes,
            ]);
        });
    }
}
