<?php

namespace Modules\Support\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Centers\Enums\CenterStatus;
use Modules\Support\Models\City;

class CityService
{
    /**
     * Get all cities.
     */
    public function getAll(): Collection
    {
        return City::query()
            ->orderBy('name')
            ->get();
    }

    /**
     * Get cities that have at least one center.
     */
    public function getWithCenters(): Collection
    {
        return City::query()
            ->whereHas('centers', function ($query) {
                $query->where('status', CenterStatus::VISIBLE);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Get a city by ID.
     */
    public function find(int $id): City
    {
        return City::query()->findOrFail($id);
    }

    /**
     * Create a city.
     */
    public function create(array $data): City
    {
        return City::query()->create($data);
    }

    /**
     * Update a city.
     */
    public function update(City $city, array $data): City
    {
        $city->update($data);

        return $city->refresh();
    }

    /**
     * Delete a city.
     */
    public function delete(City $city): bool
    {
        return $city->delete();
    }
}
