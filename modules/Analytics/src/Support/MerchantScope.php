<?php

namespace Modules\Analytics\Support;

use Illuminate\Database\Query\Builder;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Merchant\Platform;

/**
 * The shared rules of the rollups: which purchases count, and how a
 * purchase's merchant maps to an analytics `center_id` (0 = platform).
 */
final class MerchantScope
{
    /**
     * Purchases count toward revenue once confirmed or completed.
     *
     * @return list<string>
     */
    public static function countedStatuses(): array
    {
        return [PurchaseStatus::Confirmed->value, PurchaseStatus::Completed->value];
    }

    public static function centerMorph(): string
    {
        return (new Center)->getMorphClass();
    }

    public static function platformMorph(): string
    {
        return (new Platform)->getMorphClass();
    }

    public static function clientMorph(): string
    {
        return (new Client)->getMorphClass();
    }

    /**
     * Restrict a query on `purchases` (or one joining it) to center and
     * platform merchants and live rows.
     */
    public static function merchants(Builder $query, string $table = 'purchases'): Builder
    {
        return $query
            ->whereIn("{$table}.merchant_type", [self::centerMorph(), self::platformMorph()])
            ->whereNull("{$table}.deleted_at");
    }

    /**
     * Restrict a query on `purchases` to counted purchases.
     */
    public static function counted(Builder $query, string $table = 'purchases'): Builder
    {
        return self::merchants($query, $table)->whereIn("{$table}.status", self::countedStatuses());
    }

    /**
     * The analytics merchant id of a grouped row's merchant type and id.
     */
    public static function merchantKey(string $merchantType, int|string $merchantId): int
    {
        return $merchantType === self::platformMorph() ? 0 : (int) $merchantId;
    }
}
