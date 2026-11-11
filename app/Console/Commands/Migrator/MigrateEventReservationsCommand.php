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

#[Signature('migrator:event-reservations {--dry-run : Run inside a transaction and roll it back}')]
#[Description('Copy legacy event reservations into reservations with one ticket line, mapping their ids')]
class MigrateEventReservationsCommand extends LegacyMigrator
{
    public const ENTITY = 'reservation:event';

    protected function migrate(): void
    {
        $purchases = new LegacyPurchases($this->legacy());
        $migrated = $this->idMap(self::ENTITY);
        $serviceMap = $this->idMap(MigrateServicesCommand::ENTITY_EVENT);
        $services = DB::table('services')->get(['id', 'center_id'])->keyBy('id');
        $events = DB::table('events')->get(['id', 'start_date', 'end_date'])->keyBy('id');
        $options = DB::table('price_options')->whereNull('deleted_at')->orderBy('id')->get(['id', 'service_id', 'name'])->groupBy('service_id');

        $this->chunkLegacy('event_reservations', function (Collection $rows) use ($purchases, $migrated, $serviceMap, $services, $events, $options): void {
            foreach ($rows as $row) {
                if (isset($migrated[$row->id])) {
                    $this->tally('event reservations already present');

                    continue;
                }

                $purchase = $purchases->forSource(LegacyPurchases::EVENT_RESERVATION, $row->id);
                $service = $services->get($serviceMap[$row->event_id] ?? 0);
                $event = $events->get($row->event_id);
                $clientId = $this->migratedClientId($purchase?->client_id);

                if ($purchase === null || $service === null || $event === null || $clientId === null) {
                    $this->addWarning("Event reservation #{$row->id}: no purchase, event or client migrated; skipped.");

                    continue;
                }

                $category = json_decode((string) $row->category, true);
                $category = is_array($category) ? $category : [];
                $optionId = $options->get($service->id, collect())
                    ->first(fn (object $option): bool => $option->name !== null && $option->name === ($category['name'] ?? null))?->id;

                if ($optionId === null) {
                    $this->tally('event reservations without a matching ticket option');
                }

                $date = $row->date ?? $event->start_date;
                $endDate = $row->date === null ? $event->end_date : null;
                $status = LegacyPurchases::bookingStatus($purchase, Carbon::parse($endDate ?? $date));
                $quantity = max(1, (int) $row->tickets);
                $unitPrice = $this->halalas($category['price'] ?? 0);

                $reservationId = DB::table('reservations')->insertGetId([
                    'code' => $this->uniqueCode('reservations'),
                    'center_id' => $service->center_id,
                    'service_id' => $service->id,
                    'client_id' => $clientId,
                    'price_option_id' => $optionId,
                    'source' => (LegacyPurchases::isCenterMade($purchase) ? ReservationSource::Center : ReservationSource::Client)->value,
                    'status' => $status->value,
                    'date' => $date,
                    'end_date' => $endDate,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $unitPrice * $quantity,
                    'total_amount' => $unitPrice * $quantity,
                    'cancelled_at' => $status === ReservationStatus::Cancelled ? LegacyPurchases::cancelledAt($purchase) : null,
                    'metadata' => json_encode(['legacy_category' => $category], JSON_UNESCAPED_UNICODE),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);

                DB::table('reservation_lines')->insert([
                    'reservation_id' => $reservationId,
                    'price_option_id' => $optionId,
                    'kind' => ReservationLineKind::Ticket->value,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $unitPrice * $quantity,
                ]);

                $this->mapId(self::ENTITY, $row->id, (int) $reservationId);
                $this->tally('event reservations');

                if ($row->date === null) {
                    $this->tally('event reservations dated from the event');
                }
            }
        });
    }
}
