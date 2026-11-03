<?php

namespace Modules\Promotions\Data;

/**
 * A purchase line described only by the facts promotions care about. Money is in minor units.
 */
final readonly class DiscountableLine
{
    public function __construct(
        public int $index,
        public int $centerId,
        public ?int $serviceId,
        public int $unitPrice,
        public int $quantity = 1,
    ) {}

    public function subtotal(): int
    {
        return $this->unitPrice * $this->quantity;
    }
}
