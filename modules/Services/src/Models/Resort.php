<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Services\Concerns\HasService;
use Modules\Services\Concerns\HasSettings;
use Modules\Services\Concerns\HasStartPrice;

class Resort extends Model
{
    use HasFactory, HasService, HasSettings, HasStartPrice, SoftDeletes;

    public $timestamps = false;

    const array SETTINGS_KEYS = [
        'enable_cash_on_arrival',
        'close_all_training_bookings',
        'close_all_visit_bookings',
        'close_all_recreational_ride_bookings',
        'close_all_resort_bookings',
    ];

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
