<?php

namespace Modules\Analytics\Tests\Support;

use Modules\Centers\Models\Center;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Clients\Models\Client;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Merchant\Platform;
use Modules\Purchase\Models\Purchase;

final class AnalyticsFixtures
{
    /**
     * A confirmed client purchase from a center, or from the platform when `$merchant` is null.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function purchase(?Center $merchant, ?Client $buyer = null, array $attributes = []): Purchase
    {
        $buyer ??= ClientFactory::new()->create();
        $merchant ??= new Platform;

        return PurchaseFactory::new()->create([
            'buyer_type' => $buyer->getMorphClass(),
            'buyer_id' => $buyer->getKey(),
            'merchant_type' => $merchant->getMorphClass(),
            'merchant_id' => $merchant->getKey(),
            'source' => PurchaseSource::Client,
            'status' => PurchaseStatus::Confirmed,
            'discount_amount' => 0,
            ...$attributes,
        ]);
    }
}
