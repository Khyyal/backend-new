<?php

namespace App\Console\Commands\Migrator\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Reservations\Enums\ReservationStatus;

/**
 * Read model over the legacy `purchases` table. Every legacy booking (slot
 * reservation, package, event, resort or membership) is the `source` of
 * exactly one purchase, which is also the only place the client is stored.
 */
final class LegacyPurchases
{
    public const RESERVATION = 'App\Models\Reservation';

    public const CLIENT_SESSIONS = 'App\Models\ClientSessions';

    public const EVENT_RESERVATION = 'App\Models\EventReservation';

    public const RESORT_RESERVATION = 'App\Models\ResortReservation';

    public const MEMBERSHIP_RESERVATION = 'App\Models\MembershipReservation';

    private const BARN = 'App\Models\Barn';

    /** @var Collection<string, object> */
    private Collection $bySource;

    /** @var Collection<int, object> */
    private Collection $byId;

    public function __construct(Connection $legacy)
    {
        $purchases = $legacy->table('purchases')->orderBy('id')->get();

        $this->byId = $purchases->keyBy('id');
        $this->bySource = $purchases->keyBy(fn (object $purchase): string => "{$purchase->source_type}|{$purchase->source_id}");
    }

    public function forSource(string $type, int $id): ?object
    {
        return $this->bySource->get("{$type}|{$id}");
    }

    public function find(?int $id): ?object
    {
        return $id === null ? null : $this->byId->get($id);
    }

    /**
     * @return Collection<int, object>
     */
    public function all(): Collection
    {
        return $this->byId;
    }

    /**
     * completed and refunded → confirmed (a refund is recorded on the payment),
     * pending → pending, canceled → cancelled.
     */
    public static function status(object $purchase): PurchaseStatus
    {
        return match ($purchase->status) {
            'completed', 'refunded' => PurchaseStatus::Confirmed,
            'canceled', 'cancelled' => PurchaseStatus::Cancelled,
            default => PurchaseStatus::Pending,
        };
    }

    public static function isRefunded(object $purchase): bool
    {
        return $purchase->status === 'refunded';
    }

    public static function isCenterMade(object $purchase): bool
    {
        return $purchase->created_by_type === self::BARN;
    }

    /**
     * Status of an event, resort or membership booking, whose own legacy
     * status is not reliable: refunded or cancelled purchases cancel it,
     * pending stays pending, and a paid one is completed once it is over.
     */
    public static function bookingStatus(object $purchase, CarbonInterface $lastDay): ReservationStatus
    {
        if (self::isRefunded($purchase) || self::status($purchase) === PurchaseStatus::Cancelled) {
            return ReservationStatus::Cancelled;
        }

        if (self::status($purchase) === PurchaseStatus::Pending) {
            return ReservationStatus::Pending;
        }

        return $lastDay->endOfDay()->isPast() ? ReservationStatus::Completed : ReservationStatus::Confirmed;
    }

    /**
     * What the client was charged (SAR): the tax-inclusive total, falling
     * back to the discounted price, then the list price.
     */
    public static function total(object $purchase): float
    {
        return (float) ($purchase->total_price_tax_added ?? $purchase->price_after_discount ?? $purchase->total_price ?? 0);
    }

    /**
     * When the legacy purchase was cancelled or refunded.
     */
    public static function cancelledAt(object $purchase): mixed
    {
        return $purchase->canceled_at ?? $purchase->refunded_at ?? $purchase->updated_at;
    }
}
