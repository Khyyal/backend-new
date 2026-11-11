<?php

namespace App\Console\Commands\Migrator;

use App\Console\Commands\Migrator\Support\LegacyPurchases;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Reservations\Models\ClientMembership;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Settlements\Actions\SyncPurchaseLedger;
use Throwable;

#[Signature('migrator:purchases {--dry-run : Run inside a transaction and roll it back} {--skip-ledger : Do not sync the center ledger or rebuild analytics afterwards}')]
#[Description('Copy legacy purchases with one item, a payment and a fee row each, then sync the center ledger, rebuild analytics and leave each center awaiting a financial baseline')]
class MigratePurchasesCommand extends LegacyMigrator
{
    public const ENTITY = 'purchase';

    /** @var array<int, string> Legacy payment_information.method values that name a gateway. */
    private const GATEWAYS = ['moyasar', 'tamara'];

    /** @var array<int, int> */
    private array $migratedIds = [];

    /** @var array<int, true> Centers that received a migrated purchase in this run. */
    private array $migratedCenterIds = [];

    private ?string $earliestDate = null;

    protected function migrate(): void
    {
        $purchases = new LegacyPurchases($this->legacy());
        $existing = DB::table('purchases')->pluck('id')->flip();
        $snapshots = $this->legacy()->table('purchase_financial_snapshots')->get()->keyBy('purchase_id');
        $sessionReservations = $this->legacy()->table('reservations')->whereNotNull('clients_sessions_id')->pluck('clients_sessions_id', 'id');
        $sessionPurchases = $this->legacy()->table('clients_sessions')->pluck('purchase_id', 'id');
        $targets = $this->targets();

        foreach ($purchases->all() as $purchase) {
            if ($existing->has($purchase->id) || $this->mappedId(self::ENTITY, $purchase->id) !== null) {
                $this->tally('purchases already present');

                continue;
            }

            if ($purchase->source_type === LegacyPurchases::RESERVATION && $sessionReservations->has($purchase->source_id)) {
                $packagePurchaseId = $sessionPurchases[$sessionReservations[$purchase->source_id]] ?? null;

                if ($packagePurchaseId !== null) {
                    $this->mapId(self::ENTITY, $purchase->id, (int) $packagePurchaseId);
                }

                $this->tally('package session purchases folded into the package purchase');

                continue;
            }

            $target = $this->target($purchase, $targets);

            if ($target === null) {
                $this->tally("purchases skipped ({$this->skipReason($purchase)})");

                continue;
            }

            $buyerId = $this->migratedClientId($purchase->client_id);

            if ($buyerId === null) {
                $this->addWarning("Purchase #{$purchase->id}: client #{$purchase->client_id} was not migrated; skipped.");

                continue;
            }

            $this->insertPurchase($purchase, $buyerId, $target, $snapshots->get($purchase->id));
        }
    }

    protected function afterCommit(bool $dryRun): void
    {
        if ($dryRun || $this->migratedIds === []) {
            return;
        }

        $this->requireFinancialBaselines();

        if ($this->option('skip-ledger')) {
            return;
        }

        $ledger = app(SyncPurchaseLedger::class);

        foreach ($this->migratedIds as $purchaseId) {
            try {
                $ledger->execute($purchaseId);
                $this->tally('purchases synced to the center ledger');
            } catch (Throwable $exception) {
                $this->addWarning("Purchase #{$purchaseId}: ledger sync failed ({$exception->getMessage()}).");
            }
        }

        $from = Carbon::parse($this->earliestDate)->toDateString();

        if ($from < now()->toDateString()) {
            $this->call('analytics:build', ['--from' => $from]);
        }
    }

    /**
     * Legacy payouts are not migrated, so a center's migrated balance is not
     * what it is really owed. A pending baseline blocks its payouts until
     * finance confirms the true figures; an existing one is left as it is.
     */
    private function requireFinancialBaselines(): void
    {
        $rows = array_map(fn (int $centerId): array => [
            'center_id' => $centerId,
            'created_at' => now(),
            'updated_at' => now(),
        ], array_keys($this->migratedCenterIds));

        $this->tally('centers awaiting a financial baseline', DB::table('center_financial_baselines')->insertOrIgnore($rows));
    }

    /**
     * @param  array{type: string, id: int, center_id: int, service_id: int, metadata: array<string, int>}  $target
     */
    private function insertPurchase(object $purchase, int $buyerId, array $target, ?object $snapshot): void
    {
        $status = LegacyPurchases::status($purchase);
        $isCenter = LegacyPurchases::isCenterMade($purchase);
        $channel = $isCenter ? PurchaseChannel::CenterDashboard : ($purchase->channel === 'web' ? PurchaseChannel::KhyyalWeb : PurchaseChannel::KhyyalApp);
        $subtotal = $this->halalas($purchase->total_price);
        $discount = $this->halalas($purchase->discount);
        $tax = $this->halalas($purchase->tax_amount);
        $total = $this->halalas(LegacyPurchases::total($purchase));

        if ($total !== max(0, $subtotal - $discount + $tax)) {
            $this->addWarning("Purchase #{$purchase->id}: legacy total does not equal subtotal - discount + tax; copied as is.");
        }

        DB::table('purchases')->insert([
            'id' => $purchase->id,
            'code' => $this->uniqueCode('purchases'),
            'buyer_type' => (new Client)->getMorphClass(),
            'buyer_id' => $buyerId,
            'merchant_type' => (new Center)->getMorphClass(),
            'merchant_id' => $target['center_id'],
            'source' => ($isCenter ? PurchaseSource::Center : PurchaseSource::Client)->value,
            'channel' => $channel->value,
            'status' => $status->value,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'coupon_code' => blank($purchase->coupon_code) ? null : Str::limit($purchase->coupon_code, 32, ''),
            'metadata' => json_encode(array_filter([
                'legacy_code' => $purchase->code,
                'note' => $purchase->note,
                'cancel_note' => $purchase->cancel_note,
                'refund_note' => $purchase->refund_note,
                'card_type' => $purchase->card_type,
                'payment_information' => $this->paymentInformation($purchase),
            ], fn (mixed $value): bool => filled($value)), JSON_UNESCAPED_UNICODE),
            'created_at' => $purchase->created_at,
            'updated_at' => $purchase->updated_at,
        ]);

        DB::table('purchase_items')->insert([
            'purchase_id' => $purchase->id,
            'purchasable_type' => $target['type'],
            'purchasable_id' => $target['id'],
            'name' => Str::limit($purchase->name ?: $this->serviceName($target['service_id']), 255, ''),
            'quantity' => 1,
            'unit_price' => $subtotal,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'metadata' => json_encode($target['metadata']),
            'created_at' => $purchase->created_at,
            'updated_at' => $purchase->updated_at,
        ]);

        if ($total > 0) {
            $this->insertPayment($purchase, $total, $status, $snapshot);
        }

        $isReversed = $status === PurchaseStatus::Cancelled || LegacyPurchases::isRefunded($purchase);

        DB::table('purchase_fees')->insert([
            'purchase_id' => $purchase->id,
            'fee_rule_id' => null,
            'channel' => $channel->value,
            'base_amount' => $snapshot !== null ? $this->halalas($snapshot->purchase_amount) : $total,
            'percentage_bps' => $snapshot !== null ? (int) round((float) $snapshot->platform_commission_percentage * 100) : 0,
            'fixed_amount' => 0,
            'amount' => $snapshot !== null ? $this->halalas($snapshot->platform_share) : 0,
            'reversed_at' => $isReversed ? LegacyPurchases::cancelledAt($purchase) : null,
            'created_at' => $purchase->created_at,
        ]);

        $this->migratedIds[] = $purchase->id;
        $this->migratedCenterIds[$target['center_id']] = true;
        $this->earliestDate = min($this->earliestDate ?? (string) $purchase->created_at, (string) $purchase->created_at);
        $this->tally("purchases ({$status->value})");
        $this->tally($snapshot !== null ? 'fee rows from legacy snapshots' : 'zero fee rows');
    }

    private function insertPayment(object $purchase, int $amount, PurchaseStatus $status, ?object $snapshot): void
    {
        $information = $this->paymentInformation($purchase);
        $isRefunded = LegacyPurchases::isRefunded($purchase);
        $paymentStatus = match (true) {
            $isRefunded => PaymentStatus::Refunded,
            $status === PurchaseStatus::Confirmed => PaymentStatus::Succeeded,
            $status === PurchaseStatus::Cancelled => PaymentStatus::Cancelled,
            default => PaymentStatus::Pending,
        };
        $gateway = strtolower((string) ($information['method'] ?? ''));

        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid7(),
            'purchase_id' => $purchase->id,
            'amount' => $amount,
            'refunded_amount' => $isRefunded ? $amount : 0,
            'provider_fee_amount' => $snapshot !== null ? $this->halalas($snapshot->gateway_fee) : null,
            'method' => ($purchase->pay_when_arrived ? PaymentMethod::CashOnArrival : PaymentMethod::Online)->value,
            'status' => $paymentStatus->value,
            'payment_company' => in_array($gateway, self::GATEWAYS, true) ? $gateway : null,
            'payment_type' => $information['type'] ?? null,
            'payment_order_id' => $purchase->order_id,
            'provider_data' => $information === [] ? null : json_encode($information, JSON_UNESCAPED_UNICODE),
            'metadata' => null,
            'paid_at' => in_array($paymentStatus, [PaymentStatus::Succeeded, PaymentStatus::Refunded], true) ? $purchase->created_at : null,
            'refunded_at' => $isRefunded ? ($purchase->refunded_at ?? $purchase->updated_at) : null,
            'created_at' => $purchase->created_at,
            'updated_at' => $purchase->updated_at,
        ]);

        $this->tally("payments ({$paymentStatus->value})");
    }

    /**
     * What each migrated booking can be bought as, keyed by legacy source.
     *
     * @return array<string, array<int, array{type: string, id: int, center_id: int, service_id: int, metadata: array<string, int>}>>
     */
    private function targets(): array
    {
        $centerOf = DB::table('services')->pluck('center_id', 'id');
        $reservationType = (new Reservation)->getMorphClass();
        $reservations = DB::table('reservations')->get(['id', 'service_id', 'center_id', 'quantity', 'client_package_id'])->keyBy('id');
        $sessionCounts = DB::table('client_packages')->whereNotNull('client_membership_id')
            ->selectRaw('client_membership_id, count(*) as packages')->groupBy('client_membership_id')->pluck('packages', 'client_membership_id');

        $asReservation = fn (object $reservation): array => [
            'type' => $reservationType,
            'id' => $reservation->id,
            'center_id' => $reservation->center_id,
            'service_id' => $reservation->service_id,
            'metadata' => ['quantity' => (int) $reservation->quantity],
        ];

        $mapped = fn (string $entity): array => collect($this->idMap($entity))
            ->filter(fn (int $newId): bool => $reservations->has($newId))
            ->map(fn (int $newId): array => $asReservation($reservations[$newId]))
            ->all();

        return [
            LegacyPurchases::RESERVATION => $reservations
                ->filter(fn (object $reservation): bool => $reservation->client_package_id === null)
                ->map($asReservation)->all(),
            LegacyPurchases::EVENT_RESERVATION => $mapped(MigrateEventReservationsCommand::ENTITY),
            LegacyPurchases::RESORT_RESERVATION => $mapped(MigrateResortReservationsCommand::ENTITY),
            LegacyPurchases::CLIENT_SESSIONS => DB::table('client_packages')->whereNull('client_membership_id')->get(['id', 'service_id', 'total_sessions'])
                ->keyBy('id')
                ->map(fn (object $package): array => [
                    'type' => (new ClientPackage)->getMorphClass(),
                    'id' => $package->id,
                    'center_id' => (int) $centerOf[$package->service_id],
                    'service_id' => $package->service_id,
                    'metadata' => ['sessions' => (int) $package->total_sessions],
                ])->all(),
            LegacyPurchases::MEMBERSHIP_RESERVATION => DB::table('client_memberships')->get(['id', 'service_id'])
                ->keyBy('id')
                ->map(fn (object $membership): array => [
                    'type' => (new ClientMembership)->getMorphClass(),
                    'id' => $membership->id,
                    'center_id' => (int) $centerOf[$membership->service_id],
                    'service_id' => $membership->service_id,
                    'metadata' => ['packages' => (int) ($sessionCounts[$membership->id] ?? 0)],
                ])->all(),
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $targets
     * @return array{type: string, id: int, center_id: int, service_id: int, metadata: array<string, int>}|null
     */
    private function target(object $purchase, array $targets): ?array
    {
        return $targets[$purchase->source_type][$purchase->source_id] ?? null;
    }

    private function skipReason(object $purchase): string
    {
        return in_array($purchase->source_type, [
            LegacyPurchases::RESERVATION,
            LegacyPurchases::CLIENT_SESSIONS,
            LegacyPurchases::EVENT_RESERVATION,
            LegacyPurchases::RESORT_RESERVATION,
            LegacyPurchases::MEMBERSHIP_RESERVATION,
        ], true) ? 'booking not migrated' : class_basename($purchase->source_type);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentInformation(object $purchase): array
    {
        $information = json_decode((string) $purchase->payment_information, true);

        return is_array($information) ? $information : [];
    }

    private function serviceName(int $serviceId): string
    {
        $name = json_decode((string) DB::table('services')->where('id', $serviceId)->value('name'), true);

        return is_array($name) ? (string) ($name['ar'] ?? $name['en'] ?? "Service #{$serviceId}") : "Service #{$serviceId}";
    }
}
