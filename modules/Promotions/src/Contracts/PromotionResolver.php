<?php

namespace Modules\Promotions\Contracts;

use Carbon\CarbonInterface;
use Modules\Clients\Models\Client;
use Modules\Promotions\Data\DiscountableLine;
use Modules\Promotions\Data\DiscountResult;

interface PromotionResolver
{
    /**
     * Work out the discounts for a purchase at one center: the live automatic
     * offers plus, when a code is given, the coupon's offer. Reads only;
     * nothing is persisted or reserved.
     *
     * @param  list<DiscountableLine>  $lines
     */
    public function resolve(
        int $centerId,
        array $lines,
        ?CarbonInterface $at = null,
        ?Client $client = null,
        ?string $couponCode = null,
    ): DiscountResult;
}
