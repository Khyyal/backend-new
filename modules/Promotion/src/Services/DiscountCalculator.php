<?php

namespace Modules\Promotion\Services;

use Modules\Promotion\Enums\DiscountType;
use Modules\Promotion\Models\Discount;

class DiscountCalculator
{
    public function calculate(Discount $discount, iterable $items, float|int $subtotal): float
    {
        $amount = match ($discount->type) {
            DiscountType::Fixed => $this->calculateFixed($discount, (float) $subtotal),
            DiscountType::Percentage => $this->calculatePercentage($discount, (float) $subtotal),
        };

        $capped = min($amount, (float) $subtotal);

        return round(max(0, $capped), 2);
    }

    private function calculateFixed(Discount $discount, float $subtotal): float
    {
        return (float) $discount->value;
    }

    private function calculatePercentage(Discount $discount, float $subtotal): float
    {
        $raw = $subtotal * ((float) $discount->value / 100);

        if ($discount->maximum_discount !== null) {
            return min($raw, (float) $discount->maximum_discount);
        }

        return $raw;
    }
}
