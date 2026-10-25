<?php

namespace Modules\Services\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasStartPrice
{
    public function scopeWithStartPrice(Builder $query): Builder
    {
        return $query->withMin($this->startPriceRelation().' as start_price', 'price');
    }

    protected function startPrice(): Attribute
    {
        return Attribute::get(function () {
            if (array_key_exists('start_price', $this->attributes)) {
                return $this->attributes['start_price'] !== null ? (float) $this->attributes['start_price'] : null;
            }

            $relation = $this->startPriceRelation();

            if ($this->relationLoaded($relation)) {
                return $this->{$relation}->min('price');
            }

            return $this->{$relation}()->min('price');
        });
    }

    protected function startPriceRelation(): string
    {
        return 'priceOptions';
    }
}
