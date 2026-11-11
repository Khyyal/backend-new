<?php

namespace Modules\Reservations\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\ClientMembership;

/**
 * Pushes a client membership's expiry later. An expired membership becomes
 * active again; the packages that came with it keep their own validity.
 */
class ExtendClientMembership
{
    /**
     * @throws ReservationNotAllowedException
     */
    public function execute(ClientMembership $clientMembership, CarbonImmutable $expiresAt): ClientMembership
    {
        return DB::transaction(function () use ($clientMembership, $expiresAt): ClientMembership {
            $membership = ClientMembership::query()->whereKey($clientMembership->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($membership->status, [ClientMembershipStatus::Pending, ClientMembershipStatus::Cancelled], true)) {
                throw ReservationNotAllowedException::because('membership_not_adjustable');
            }

            if ($membership->expires_at !== null && ! $expiresAt->isAfter($membership->expires_at)) {
                throw ReservationNotAllowedException::because('invalid_expiry');
            }

            $membership->expires_at = $expiresAt;
            $membership->expiry_reminder_sent_at = null;

            if ($membership->status === ClientMembershipStatus::Expired) {
                $membership->status = ClientMembershipStatus::Active;
            }

            $membership->save();

            return $membership;
        });
    }
}
