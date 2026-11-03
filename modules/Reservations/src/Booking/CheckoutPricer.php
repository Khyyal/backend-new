<?php

namespace Modules\Reservations\Booking;

use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Promotions\Contracts\PromotionResolver;
use Modules\Promotions\Data\DiscountableLine;

/**
 * Prices checkout items with the promotions running at the center. Reads
 * only; usage is consumed when the purchase is created (StartCheckout).
 */
class CheckoutPricer
{
    public function __construct(
        private PromotionResolver $promotions,
    ) {}

    /**
     * @param  array<int, array{service_id?: int|null, unit_price: int, quantity?: int}>  $items  see CreatePurchase::execute()
     */
    public function quote(Center $center, Client $client, array $items, ?string $couponCode = null): CheckoutQuote
    {
        $items = array_values($items);
        $lines = [];
        $subtotals = [];

        foreach ($items as $index => $item) {
            $line = new DiscountableLine(
                index: $index,
                centerId: (int) $center->getKey(),
                serviceId: isset($item['service_id']) ? (int) $item['service_id'] : null,
                unitPrice: (int) $item['unit_price'],
                quantity: (int) ($item['quantity'] ?? 1),
            );

            $lines[] = $line;
            $subtotals[] = $line->subtotal();
        }

        return new CheckoutQuote(
            $subtotals,
            $this->promotions->resolve((int) $center->getKey(), $lines, now(), $client, $couponCode),
        );
    }
}
