<?php

namespace App\Console\Commands\Migrator;

use App\Console\Commands\Migrator\Support\LegacyPurchases;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Reservations\Enums\ClientPackageStatus;

#[Signature('migrator:client-packages {--dry-run : Run inside a transaction and roll it back}')]
#[Description('Copy legacy client sessions (bought packages) into client packages, keeping their ids and recounting remaining sessions')]
class MigrateClientPackagesCommand extends LegacyMigrator
{
    protected function migrate(): void
    {
        $purchases = new LegacyPurchases($this->legacy());
        $existing = DB::table('client_packages')->pluck('id')->flip();
        $packages = DB::table('service_packages')->get(['id', 'service_id', 'price_option_id'])->keyBy('id');
        $packageMap = $this->idMap(MigrateServicesCommand::ENTITY_SERVICE_PACKAGE);
        $trainerIds = DB::table('trainers')->pluck('id')->flip();
        $membershipIds = DB::table('client_memberships')->pluck('id')->flip();
        $used = $this->legacy()->table('reservations')
            ->whereNotNull('clients_sessions_id')
            ->where('status', '!=', 'canceled')
            ->selectRaw('clients_sessions_id, count(*) as used')
            ->groupBy('clients_sessions_id')
            ->pluck('used', 'clients_sessions_id');

        $this->chunkLegacy('clients_sessions', function (Collection $rows) use ($purchases, $existing, $packages, $packageMap, $trainerIds, $membershipIds, $used): void {
            foreach ($rows as $row) {
                if ($existing->has($row->id)) {
                    $this->tally('client packages already present');

                    continue;
                }

                $purchase = $purchases->find($row->purchase_id);
                $clientId = $this->migratedClientId($purchase?->client_id);
                $package = $packages->get($packageMap[$row->session_id] ?? 0);

                if ($purchase === null || $clientId === null || $package === null) {
                    $this->addWarning("Client session #{$row->id}: no purchase, client or package tier migrated; skipped.");

                    continue;
                }

                $remaining = max(0, (int) $row->count - (int) ($used[$row->id] ?? 0));
                $membershipId = $row->membership_reservation_id !== null && $membershipIds->has($row->membership_reservation_id)
                    ? $row->membership_reservation_id
                    : null;

                if ($row->membership_reservation_id !== null && $membershipId === null) {
                    $this->addWarning("Client session #{$row->id}: membership reservation #{$row->membership_reservation_id} was not migrated; kept as a standalone package.");
                }

                DB::table('client_packages')->insert([
                    'id' => $row->id,
                    'client_id' => $clientId,
                    'service_id' => $package->service_id,
                    'service_package_id' => $package->id,
                    'price_option_id' => $package->price_option_id,
                    'client_membership_id' => $membershipId,
                    'trainer_id' => $row->trainer_id !== null && $trainerIds->has($row->trainer_id) ? $row->trainer_id : null,
                    'total_sessions' => (int) $row->count,
                    'remaining_sessions' => $remaining,
                    'price' => $row->membership_reservation_id !== null ? 0 : $this->halalas(LegacyPurchases::total($purchase)),
                    'status' => $this->status($row, $purchase, $remaining)->value,
                    'expires_at' => $row->end_date,
                    'knight_name' => $row->knight_name,
                    'controlled' => (bool) $row->controlled,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);

                $this->tally('client packages');
            }
        });
    }

    /**
     * Cancelled when the legacy row or its purchase was cancelled, exhausted
     * when no session is left, expired past its end date, pending while the
     * purchase is unpaid, otherwise active.
     */
    private function status(object $row, object $purchase, int $remaining): ClientPackageStatus
    {
        $purchaseStatus = LegacyPurchases::status($purchase);

        return match (true) {
            $row->status === 'canceled' || $purchaseStatus === PurchaseStatus::Cancelled => ClientPackageStatus::Cancelled,
            $remaining === 0 => ClientPackageStatus::Exhausted,
            $row->end_date !== null && Carbon::parse($row->end_date)->isPast() => ClientPackageStatus::Expired,
            $purchaseStatus === PurchaseStatus::Pending => ClientPackageStatus::Pending,
            default => ClientPackageStatus::Active,
        };
    }
}
