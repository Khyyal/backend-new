<?php

namespace Modules\Promotions\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Services\Models\Service;

interface ServicePriceDisplay
{
    /**
     * Discounted "from" prices for services, keyed by service id. Services
     * without a start price or an applicable automatic item offer are omitted.
     * Amounts are minor units.
     *
     * @param  Collection<int, Service>  $services  with a `start_price` attribute (e.g. withMin on priceOptions)
     * @return array<int, array{original: int, discounted: int, discount_amount: int, badge: string}>
     */
    public function forServices(Collection $services, ?CarbonInterface $at = null): array;
}
