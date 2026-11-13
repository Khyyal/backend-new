<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Services\Concerns\HasService;
use Modules\Services\Concerns\HasStartPrice;
use Modules\Services\Enums\PriceOptionUnit;

/**
 * Horse stabling (boarding). Its prices are stored as price options:
 * monthly prices (unit `month`, quantity = months, at least one), an optional
 * daily price (unit `day`, quantity 1) and optional named extras (unit
 * `option`, no quantity). Extras do not count toward the start price.
 */
class Stabling extends Model
{
    use HasFactory, HasService, HasStartPrice, SoftDeletes;

    public $timestamps = false;

    public function monthlyPrices(): HasManyThrough
    {
        return $this->priceOptions()
            ->where('price_options.unit', PriceOptionUnit::MONTH->value);
    }

    public function dailyPrices(): HasManyThrough
    {
        return $this->priceOptions()
            ->where('price_options.unit', PriceOptionUnit::DAY->value);
    }

    public function extras(): HasManyThrough
    {
        return $this->priceOptions()
            ->where('price_options.unit', PriceOptionUnit::OPTION->value);
    }

    /**
     * Monthly and daily prices: every option with a quantity.
     */
    public function basePrices(): HasManyThrough
    {
        return $this->priceOptions()
            ->whereNotNull('price_options.quantity');
    }

    protected function startPriceRelation(): string
    {
        return 'basePrices';
    }
}
