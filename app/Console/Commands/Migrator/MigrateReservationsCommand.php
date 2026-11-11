<?php

namespace App\Console\Commands\Migrator;

use App\Console\Commands\Migrator\Support\LegacyPurchases;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;

#[Signature('migrator:reservations {--dry-run : Run inside a transaction and roll it back}')]
#[Description('Copy legacy training, riding and visit reservations, keeping their ids and linking package sessions to client packages')]
class MigrateReservationsCommand extends LegacyMigrator
{
    /** @var array<string, ReservationStatus> */
    private const STATUSES = [
        'active' => ReservationStatus::Confirmed,
        'finished' => ReservationStatus::Completed,
        'canceled' => ReservationStatus::Cancelled,
    ];

    protected function migrate(): void
    {
        $purchases = new LegacyPurchases($this->legacy());
        $existing = DB::table('reservations')->pluck('id')->flip();
        $services = DB::table('services')->get(['id', 'center_id', 'type'])->keyBy('id');
        $options = DB::table('price_options')->whereNull('deleted_at')->orderBy('id')->get(['id', 'service_id', 'unit', 'quantity'])->groupBy('service_id');
        $trainerIds = DB::table('trainers')->pluck('id')->flip();
        $packageIds = DB::table('client_packages')->pluck('id')->flip();
        $sessionPurchases = $this->legacy()->table('clients_sessions')->pluck('purchase_id', 'id');

        $this->chunkLegacy('reservations', function (Collection $rows) use ($purchases, $existing, $services, $options, $trainerIds, $packageIds, $sessionPurchases): void {
            foreach ($rows as $row) {
                if ($existing->has($row->id)) {
                    $this->tally('reservations already present');

                    continue;
                }

                $isPackageSession = $row->clients_sessions_id !== null;
                $purchase = $isPackageSession
                    ? $purchases->find($sessionPurchases[$row->clients_sessions_id] ?? null)
                    : $purchases->forSource(LegacyPurchases::RESERVATION, $row->id);
                $service = $services->get($row->service_id);
                $clientId = $this->migratedClientId($purchase?->client_id);

                if ($purchase === null || $service === null || $clientId === null) {
                    $this->addWarning("Reservation #{$row->id}: no purchase, service or client migrated; skipped.");

                    continue;
                }

                if ($isPackageSession && ! $packageIds->has($row->clients_sessions_id)) {
                    $this->addWarning("Reservation #{$row->id}: client session #{$row->clients_sessions_id} was not migrated; skipped.");

                    continue;
                }

                $optionId = $this->priceOptionId($service, $options->get($service->id, collect()), $row->duration);
                $metadata = ['legacy_code' => $row->code];

                if ($optionId === null) {
                    $metadata['legacy_duration'] = $row->duration;
                    $this->tally('reservations without a matching price option');
                }

                $quantity = max(1, (int) $row->tickets_booked);
                $unitPrice = $isPackageSession ? 0 : $this->halalas($row->ticket_price);
                $status = self::STATUSES[$row->status] ?? ReservationStatus::Confirmed;

                DB::table('reservations')->insert([
                    'id' => $row->id,
                    'code' => $this->uniqueCode('reservations'),
                    'center_id' => $service->center_id,
                    'service_id' => $service->id,
                    'client_id' => $clientId,
                    'trainer_id' => $row->trainer_id !== null && $trainerIds->has($row->trainer_id) ? $row->trainer_id : null,
                    'price_option_id' => $optionId,
                    'client_package_id' => $isPackageSession ? $row->clients_sessions_id : null,
                    'client_membership_id' => null,
                    'source' => (LegacyPurchases::isCenterMade($purchase) ? ReservationSource::Center : ReservationSource::Client)->value,
                    'status' => $status->value,
                    'date' => $row->day,
                    'end_date' => null,
                    'start_time' => $row->time,
                    'end_time' => $this->endTime($row->time, $row->duration),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $unitPrice * $quantity,
                    'total_amount' => $unitPrice * $quantity,
                    'cancelled_at' => $status === ReservationStatus::Cancelled ? ($purchase->canceled_at ?? $row->updated_at) : null,
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);

                $this->tally($isPackageSession ? 'package session reservations' : 'single reservations');
            }
        });
    }

    /**
     * Riding and training book the minute option of the slot length; a visit
     * has a single option.
     *
     * @param  Collection<int, object>  $options
     */
    private function priceOptionId(object $service, Collection $options, mixed $duration): ?int
    {
        if ($service->type === ServiceType::Visit->value) {
            return $options->first()?->id;
        }

        return $options->first(fn (object $option): bool => $option->unit === PriceOptionUnit::MINUTE->value
            && $duration !== null
            && (int) $option->quantity === (int) $duration)?->id;
    }

    private function endTime(?string $start, mixed $duration): ?string
    {
        if ($start === null || $duration === null || (int) $duration <= 0) {
            return null;
        }

        $startAt = Carbon::parse("2000-01-01 {$start}");
        $endAt = $startAt->copy()->addMinutes((int) $duration);

        return $endAt->isSameDay($startAt) ? $endAt->format('H:i:s') : '24:00:00';
    }
}
