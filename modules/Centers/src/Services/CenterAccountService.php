<?php

namespace Modules\Centers\Services;

use Illuminate\Support\Facades\DB;
use Modules\Centers\Exceptions\CenterDeletionNotAllowedException;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Models\Reservation;
use Modules\Settlements\Enums\CenterPayoutStatus;
use Modules\Settlements\Models\CenterLedgerEntry;
use Modules\Settlements\Models\CenterPayout;

/**
 * Self-service account actions of a center's primary user.
 */
class CenterAccountService
{
    public function __construct(private readonly CenterUserManagementService $userService) {}

    /**
     * Updates the user's own name and/or password.
     *
     * @param  array{name?: string, password?: string}  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->fill(array_intersect_key($data, array_flip(['name', 'password'])))->save();

        return $user;
    }

    /**
     * Soft-deletes the center and cuts its users' access (revoking their center
     * tokens; accounts left without another center are deleted too). Their
     * assignments are kept so an admin can restore the center with its team.
     *
     * @throws CenterDeletionNotAllowedException When upcoming reservations or an unsettled balance remain.
     */
    public function delete(Center $center): void
    {
        DB::transaction(function () use ($center): void {
            $center = Center::query()->whereKey($center->getKey())->lockForUpdate()->firstOrFail();

            $this->ensureCanDelete($center);

            $center->delete();

            $this->userService->suspendAll($center);
        });
    }

    /**
     * @throws CenterDeletionNotAllowedException
     */
    private function ensureCanDelete(Center $center): void
    {
        $hasOpenReservations = Reservation::query()
            ->where('center_id', $center->getKey())
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
            ->whereDate('date', '>=', today())
            ->exists();

        if ($hasOpenReservations) {
            throw CenterDeletionNotAllowedException::because('has_open_reservations');
        }

        $hasPendingPayout = CenterPayout::query()
            ->where('center_id', $center->getKey())
            ->where('status', CenterPayoutStatus::Pending)
            ->exists();

        $unsettledBalance = (int) CenterLedgerEntry::query()
            ->where('center_id', $center->getKey())
            ->whereNull('payout_id')
            ->sum('amount');

        if ($hasPendingPayout || $unsettledBalance !== 0) {
            throw CenterDeletionNotAllowedException::because('has_unsettled_balance');
        }
    }
}
