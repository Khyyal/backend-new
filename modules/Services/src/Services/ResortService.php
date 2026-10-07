<?php

namespace Modules\Services\Services;

use Illuminate\Support\Facades\DB;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Resort;
use Modules\Support\Enums\ActivationStatus;

class ResortService
{
    public function create(array $data): Resort
    {
        return DB::transaction(function () use ($data) {
            $name = [
                'ar' => $data['name']['ar'],
            ];
            if (isset($data['name']['en']) && $data['name']['en'] !== null) {
                $name['en'] = $data['name']['en'];
            }

            $description = [
                'ar' => $data['description']['ar'],
            ];
            if (isset($data['description']['en']) && $data['description']['en'] !== null) {
                $description['en'] = $data['description']['en'];
            }

            $resort = Resort::query()->create();

            $service = $resort->service()->create([
                'name' => $name,
                'description' => $description,
                'center_id' => $data['center_id'],
                'status' => ActivationStatus::ACTIVE,
                'type' => ServiceType::Resort,
            ]);

            foreach ($data['price_options'] as $option) {
                $service->priceOptions()->create([
                    'name' => $option['name'],
                    'price' => $option['price'],
                    'quantity' => null,
                    'unit' => PriceOptionUnit::OPTION,
                ]);
            }

            foreach ($data['days'] as $day) {
                $resort->dayPrices()->create([
                    'day_of_week' => $day['day'],
                    'price' => $day['price'],
                ]);
            }

            $resort->load(['service.priceOptions', 'dayPrices']);

            return $resort;
        });
    }

    public function update(Resort $resort, array $data): Resort
    {
        return DB::transaction(function () use ($resort, $data) {
            $service = $resort->service()
                ->lockForUpdate()
                ->firstOrFail();

            $name = [
                'ar' => $data['name']['ar'],
            ];
            if (isset($data['name']['en']) && $data['name']['en'] !== null) {
                $name['en'] = $data['name']['en'];
            }

            $description = [
                'ar' => $data['description']['ar'],
            ];
            if (isset($data['description']['en']) && $data['description']['en'] !== null) {
                $description['en'] = $data['description']['en'];
            }

            $service->update([
                'name' => $name,
                'description' => $description,
            ]);

            $service->priceOptions()->forceDelete();
            foreach ($data['price_options'] as $option) {
                $service->priceOptions()->create([
                    'name' => $option['name'],
                    'price' => $option['price'],
                    'quantity' => null,
                    'unit' => PriceOptionUnit::OPTION,
                ]);
            }

            $resort->dayPrices()->delete();
            foreach ($data['days'] as $day) {
                $resort->dayPrices()->create([
                    'day_of_week' => $day['day'],
                    'price' => $day['price'],
                ]);
            }

            $resort = $resort->fresh();
            $resort->load(['service.priceOptions', 'dayPrices']);

            return $resort;
        });
    }

    public function delete(Resort $resort): void
    {
        DB::transaction(function () use ($resort) {
            $service = $resort->service()
                ->lockForUpdate()
                ->firstOrFail();

            $resort->dayPrices()->delete();
            $service->priceOptions()->delete();
            $resort->delete();
            $service->delete();
        });
    }
}
