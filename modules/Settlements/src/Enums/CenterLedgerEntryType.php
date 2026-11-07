<?php

namespace Modules\Settlements\Enums;

/**
 * Why a center's balance moved. Credits are what the platform owes the
 * center, debits what the center owes the platform.
 */
enum CenterLedgerEntryType: string
{
    /** An online payment the platform collected for the center. */
    case Payment = 'payment';

    /** The part of a discount the platform funded, owed to the center. */
    case PlatformSubsidy = 'platform_subsidy';

    /** A platform-funded discount taken back after a cancellation or full refund. */
    case SubsidyReversal = 'subsidy_reversal';

    /** The platform fee on a confirmed purchase. */
    case PlatformFee = 'platform_fee';

    /** A platform fee given back after a cancellation or full refund. */
    case FeeReversal = 'fee_reversal';

    /** Money given back to the client from an online payment. */
    case Refund = 'refund';

    /**
     * @return array<self>
     */
    public static function feeTypes(): array
    {
        return [self::PlatformFee, self::FeeReversal];
    }

    /**
     * @return array<self>
     */
    public static function subsidyTypes(): array
    {
        return [self::PlatformSubsidy, self::SubsidyReversal];
    }
}
