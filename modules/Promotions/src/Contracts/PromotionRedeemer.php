<?php

namespace Modules\Promotions\Contracts;

use Modules\Promotions\Data\PriceAdjustment;
use Modules\Promotions\Exceptions\CouponRejectedException;
use Modules\Promotions\Exceptions\PromotionUnavailableException;

interface PromotionRedeemer
{
    /**
     * Consume usage for the promotions that discounted a purchase. Must run
     * inside the purchase's transaction so that any failure rolls it back.
     *
     * @param  list<PriceAdjustment>  $adjustments
     *
     * @throws CouponRejectedException when the coupon can no longer be used.
     * @throws PromotionUnavailableException when an automatic promotion can no longer be used.
     */
    public function redeem(int $purchaseId, int $clientId, array $adjustments): void;
}
