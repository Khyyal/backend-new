<?php

namespace Modules\Reservations\Booking;

/**
 * What the buyer sent about discounts at checkout: an optional coupon code
 * and, optionally, the total they were quoted so a price change is caught
 * before they are charged.
 */
final readonly class DiscountInput
{
    public function __construct(
        public ?string $couponCode = null,
        public ?int $expectedTotalAmount = null,
    ) {}

    public function hasCoupon(): bool
    {
        return $this->couponCode !== null && trim($this->couponCode) !== '';
    }
}
