<?php

namespace App\Console\Commands\Migrator;

use App\Console\Commands\Migrator\Support\LegacyPurchases;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Reservations\Enums\ReservationLineKind;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Enums\ReservationStatus;

#[Signature('migrator:resort-reservations {--dry-run : Run inside a transaction and roll it back}')]
#[Description('Copy legacy resort reservations into stay reservations with addon lines, mapping their ids')]
class MigrateResortReservationsCommand extends LegacyMigrator
{
    public const ENTITY = 'reservation:resort';

    protected function migrate(): void
    {
        $purchases = new LegacyPurchases($this->legacy());
        $migrated = $this->idMap(self::ENTITY);
        $services = DB::table('services')->get(['id', 'center_id'])->keyBy('id');
        $options = DB::table('price_options')->whereNull('deleted_at')->whereNotNull('name')->orderBy('id')->get(['id', 'service_id', 'name', 'price'])->groupBy('service_id');
        $addonNames = $this->legacy()->table('resort_additional_services')->pluck('name', 'id');

        $this->chunkLegacy('resort_reservations', function (Collection $rows) use ($purchases, $migrated, $services, $options, $addonNames): void {
            foreach ($rows as $row) {
                if (isset($migrated[$row->id])) {
                    $this->tally('resort reservations already present');

                    continue;
                }

                $purchase = $purchases->forSource(LegacyPurchases::RESORT_RESERVATION, $row->id);
                $service = $services->get($row->service_id);
                $clientId = $this->migratedClientId($purchase?->client_id);

                if ($purchase === null || $service === null || $clientId === null) {
                    $this->addWarning("Resort reservation #{$row->id}: no purchase, resort or client migrated; skipped.");

                    continue;
                }

                $lines = $this->addonLines($row, $options->get($service->id, collect()), $addonNames);
                $subtotal = $this->halalas($purchase->total_price);
                $status = LegacyPurchases::bookingStatus($purchase, Carbon::parse($row->end_date));

                $reservationId = DB::table('reservations')->insertGetId([
                    'code' => $this->uniqueCode('reservations'),
                    'center_id' => $service->center_id,
                    'service_id' => $service->id,
                    'client_id' => $clientId,
                    'source' => (LegacyPurchases::isCenterMade($purchase) ? ReservationSource::Center : ReservationSource::Client)->value,
                    'status' => $status->value,
                    'date' => $row->start_date,
                    'end_date' => $row->end_date,
                    'quantity' => 1,
                    'unit_price' => max(0, $subtotal - array_sum(array_column($lines, 'subtotal'))),
                    'subtotal' => $subtotal,
                    'total_amount' => $subtotal,
                    'cancelled_at' => $status === ReservationStatus::Cancelled ? LegacyPurchases::cancelledAt($purchase) : null,
                    'metadata' => json_encode(['legacy_additional_services' => json_decode((string) $row->additional_services, true)], JSON_UNESCAPED_UNICODE),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);

                if ($lines !== []) {
                    DB::table('reservation_lines')->insert(array_map(fn (array $line): array => ['reservation_id' => $reservationId, ...$line], $lines));
                }

                $this->mapId(self::ENTITY, $row->id, (int) $reservationId);
                $this->tally('resort reservations');
                $this->tally('resort addon lines', count($lines));
            }
        });
    }

    /**
     * One addon line per legacy additional service id, matched to the
     * resort's addon option by name and priced at that option.
     *
     * @param  Collection<int, object>  $options
     * @param  Collection<int, string>  $addonNames
     * @return array<int, array<string, mixed>>
     */
    private function addonLines(object $row, Collection $options, Collection $addonNames): array
    {
        $ids = json_decode((string) $row->additional_services, true);
        $lines = [];

        foreach (is_array($ids) ? $ids : [] as $addonId) {
            $name = $addonNames->get($addonId);
            $option = $options->first(fn (object $option): bool => $name !== null && $option->name === $name);

            if ($option === null) {
                $this->addWarning("Resort reservation #{$row->id}: addon #{$addonId} has no matching option; line skipped.");

                continue;
            }

            $lines[] = [
                'price_option_id' => $option->id,
                'kind' => ReservationLineKind::Addon->value,
                'quantity' => 1,
                'unit_price' => (int) $option->price,
                'subtotal' => (int) $option->price,
            ];
        }

        return $lines;
    }
}
