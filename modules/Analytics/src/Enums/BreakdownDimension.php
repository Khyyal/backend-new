<?php

namespace Modules\Analytics\Enums;

/**
 * How a day's facts are split in analytics_daily_breakdowns, and what the
 * generic columns hold for each split.
 */
enum BreakdownDimension: string
{
    /** count: counted purchases; amount: their GMV. Key `unknown` when no channel was recorded. */
    case Channel = 'channel';

    /** count: counted purchases with an item of the type; amount: those items' totals. */
    case ServiceType = 'service_type';

    /** count: payment attempts; success_count: succeeded ones; amount: succeeded amount. */
    case PaymentMethod = 'payment_method';

    /** Same columns as PaymentMethod, for online payments split by gateway. */
    case PaymentCompany = 'payment_company';

    /** Same columns as PaymentMethod, for online payments split by card / wallet type. */
    case PaymentType = 'payment_type';

    /** count: applied redemptions; amount: discount given; related_amount: GMV of those purchases. Key is the promotion id. */
    case Promotion = 'promotion';

    /** Same columns as Promotion, keyed by coupon id. */
    case Coupon = 'coupon';

    /**
     * @return array<self>
     */
    public static function paymentDimensions(): array
    {
        return [self::PaymentMethod, self::PaymentCompany, self::PaymentType];
    }
}
