<?php

namespace Modules\Reservations\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\ClientPackage;
use Modules\Services\Models\Trainer;
use Modules\Services\Models\TrainingService;

/**
 * Center-side changes to a paid client package: the `controlled` flag, the
 * trainer it is bound to and its expiry. Only the given keys are touched.
 */
class UpdateClientPackage
{
    /**
     * @param  array{controlled?: bool, trainer_id?: int|null, expires_at?: string}  $changes
     *
     * @throws ReservationNotAllowedException
     */
    public function execute(ClientPackage $clientPackage, array $changes): ClientPackage
    {
        return DB::transaction(function () use ($clientPackage, $changes): ClientPackage {
            $package = ClientPackage::query()
                ->whereKey($clientPackage->getKey())
                ->with('service')
                ->lockForUpdate()
                ->firstOrFail();

            if (array_key_exists('controlled', $changes)) {
                $package->controlled = $changes['controlled'];
            }

            if (array_key_exists('trainer_id', $changes)) {
                $this->bindTrainer($package, $changes['trainer_id']);
            }

            if (array_key_exists('expires_at', $changes)) {
                $this->extend($package, CarbonImmutable::parse($changes['expires_at']));
            }

            $package->save();

            return $package;
        });
    }

    private function bindTrainer(ClientPackage $package, ?int $trainerId): void
    {
        if ($trainerId === null) {
            $package->trainer_id = null;

            return;
        }

        $service = $package->service;

        if (! $service->serviceable instanceof TrainingService) {
            throw ReservationNotAllowedException::because('package_not_trainer_bound');
        }

        $teaches = Trainer::query()
            ->whereKey($trainerId)
            ->where('center_id', $service->center_id)
            ->whereHas('trainingServices', fn ($query) => $query->whereKey($service->serviceable_id))
            ->exists();

        if (! $teaches) {
            throw ReservationNotAllowedException::because('invalid_trainer');
        }

        $package->trainer_id = $trainerId;
    }

    private function extend(ClientPackage $package, CarbonImmutable $expiresAt): void
    {
        if (in_array($package->status, [ClientPackageStatus::Pending, ClientPackageStatus::Cancelled], true)) {
            throw ReservationNotAllowedException::because('package_not_adjustable');
        }

        if ($package->expires_at !== null && ! $expiresAt->isAfter($package->expires_at)) {
            throw ReservationNotAllowedException::because('invalid_expiry');
        }

        $package->expires_at = $expiresAt;
        $package->expiry_reminder_sent_at = null;

        if ($package->status === ClientPackageStatus::Expired && $package->remaining_sessions > 0) {
            $package->status = ClientPackageStatus::Active;
        }
    }
}
