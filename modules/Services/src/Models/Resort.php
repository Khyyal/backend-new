<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Services\Concerns\HasService;
use Modules\Services\Concerns\HasStartPrice;

class Resort extends Model
{
    use HasFactory, HasService, HasStartPrice, SoftDeletes;

    public $timestamps = false;

    public function dayPrices(): HasManyThrough
    {
        return $this->priceOptions()
            ->whereNotNull('price_options.quantity');
    }

    protected function startPriceRelation(): string
    {
        return 'dayPrices';
    }
}
