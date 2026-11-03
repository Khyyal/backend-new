<?php

namespace Modules\Reservations\Booking;

use Modules\Promotions\Data\CouponOutcome;
use Modules\Promotions\Data\DiscountResult;
use Modules\Promotions\Data\PriceAdjustment;

/**
 * A priced checkout: the purchase lines with the promotions that apply to
 * them. Money is in minor units (halalas).
 */
final readonly class CheckoutQuote
{
    /**
     * @param  list<int>  $lineSubtotals  indexed like the checkout items
     */
    public function __construct(
        public array $lineSubtotals,
        public DiscountResult $discounts,
    ) {}

    public function subtotal(): int
    {
        return array_sum($this->lineSubtotals);
    }

    public function discountAmount(): int
    {
        return $this->discounts->totalDiscount();
    }

    public function totalAmount(): int
    {
        return $this->subtotal() - $this->discountAmount();
    }

    public function lineDiscount(int $index): int
    {
        return $this->discounts->discountForLine($index);
    }

    /**
     * @return list<PriceAdjustment>
     */
    public function adjustments(): array
    {
        return $this->discounts->adjustments;
    }

    public function coupon(): ?CouponOutcome
    {
        return $this->discounts->coupon;
    }
}
