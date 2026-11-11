<?php

namespace App\Console\Commands\Migrator;

use App\Console\Commands\Migrator\Support\LegacyPurchases;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Enums\ReservationStatus;

#[Signature('migrator:client-memberships {--dry-run : Run inside a transaction and roll it back}')]
#[Description('Copy legacy membership reservations into client memberships, keeping their ids')]
class MigrateClientMembershipsCommand extends LegacyMigrator
{
    protected function migrate(): void
    {
        $purchases = new LegacyPurchases($this->legacy());
        $existing = DB::table('client_memberships')->pluck('id')->flip();
        $memberships = DB::table('memberships')->get(['id', 'visit_service_id', 'duration'])->keyBy('id');

        $this->chunkLegacy('membership_reservations', function (Collection $rows) use ($purchases, $existing, $memberships): void {
            foreach ($rows as $row) {
                if ($existing->has($row->id)) {
                    $this->tally('client memberships already present');

                    continue;
                }

                $purchase = $purchases->forSource(LegacyPurchases::MEMBERSHIP_RESERVATION, $row->id);
                $clientId = $this->migratedClientId($purchase?->client_id);
                $serviceId = $this->mappedId(MigrateServicesCommand::ENTITY_MEMBERSHIP, $row->membership_id);
                $membership = $memberships->get($row->membership_id);

                if ($purchase === null || $clientId === null || $serviceId === null || $membership === null) {
                    $this->addWarning("Membership reservation #{$row->id}: no purchase, client or membership service migrated; skipped.");

                    continue;
                }

                DB::table('client_memberships')->insert([
                    'id' => $row->id,
                    'client_id' => $clientId,
                    'service_id' => $serviceId,
                    'membership_id' => $row->membership_id,
                    'price' => $this->halalas(LegacyPurchases::total($purchase)),
                    'status' => $this->status($purchase, Carbon::parse($row->end_date))->value,
                    'visit_service_id' => $membership->visit_service_id,
                    'max_visits_per_day' => $row->daily_invitation_limit,
                    'duration' => $membership->duration,
                    'expires_at' => Carbon::parse($row->end_date)->endOfDay(),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);

                $this->tally('client memberships');
            }
        });
    }

    private function status(object $purchase, Carbon $endDate): ClientMembershipStatus
    {
        return match (LegacyPurchases::bookingStatus($purchase, $endDate)) {
            ReservationStatus::Cancelled => ClientMembershipStatus::Cancelled,
            ReservationStatus::Pending => ClientMembershipStatus::Pending,
            ReservationStatus::Completed => ClientMembershipStatus::Expired,
            default => ClientMembershipStatus::Active,
        };
    }
}
