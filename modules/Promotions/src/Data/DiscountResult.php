<?php

namespace Modules\Promotions\Data;

final readonly class DiscountResult
{
    /**
     * @param  array<int, int>  $lineDiscounts  Discount per line index (every line present).
     * @param  list<PriceAdjustment>  $adjustments
     */
    public function __construct(
        public array $lineDiscounts,
        public array $adjustments,
        public ?CouponOutcome $coupon = null,
    ) {}

    public function totalDiscount(): int
    {
        return array_sum($this->lineDiscounts);
    }

    public function discountForLine(int $index): int
    {
        return $this->lineDiscounts[$index] ?? 0;
    }

    /**
     * @return list<int>
     */
    public function promotionIdsForLine(int $index): array
    {
        $ids = [];

        foreach ($this->adjustments as $adjustment) {
            if ($adjustment->lineIndex === null || $adjustment->lineIndex === $index) {
                $ids[$adjustment->promotionId] = $adjustment->promotionId;
            }
        }

        return array_values($ids);
    }

    public function withCoupon(CouponOutcome $coupon): self
    {
        return new self($this->lineDiscounts, $this->adjustments, $coupon);
    }
}
