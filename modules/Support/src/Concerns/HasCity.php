<?php

namespace Modules\Support\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Support\Models\City;

trait HasCity
{
    /**
     * Get the city that owns the model.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Scope the query to a specific city.
     */
    public function scopeInCity($query, City|int $city)
    {
        return $query->where(
            $this->qualifyColumn('city_id'),
            $city instanceof City ? $city->getKey() : $city
        );
    }

    /**
     * Scope the query to models that have a city.
     */
    public function scopeHasCity($query)
    {
        return $query->whereNotNull(
            $this->qualifyColumn('city_id')
        );
    }

    /**
     * Scope the query to models without a city.
     */
    public function scopeWithoutCity($query)
    {
        return $query->whereNull(
            $this->qualifyColumn('city_id')
        );
    }

    /**
     * Assign a city to the model.
     */
    public function assignCity(City|int|null $city): static
    {
        $this->city_id = $city instanceof City
            ? $city->getKey()
            : $city;

        return $this;
    }

    /**
     * Remove the city from the model.
     */
    public function removeCity(): static
    {
        $this->city_id = null;

        return $this;
    }

    /**
     * Check whether the model belongs to a city.
     */
    public function hasCity(): bool
    {
        return $this->city_id !== null;
    }

    /**
     * Check whether the model belongs to the given city.
     */
    public function belongsToCity(City|int $city): bool
    {
        $cityId = $city instanceof City
            ? $city->getKey()
            : $city;

        return (int) $this->city_id === (int) $cityId;
    }
}
